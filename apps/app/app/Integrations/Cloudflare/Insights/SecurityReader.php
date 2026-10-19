<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Insights;

use App\Domain\Traffic\PeoplePages;
use App\Domain\Traffic\SecurityReport;
use App\Integrations\Cloudflare\CloudflareGraphql;
use App\Integrations\Cloudflare\Tally;
use DateTimeImmutable;

/**
 * Threats from the daily totals, and the firewall's own events. The free plan has no grouped firewall events, so
 * the single events of the last 30 days are read, up to Cloudflare's limit, and added up here. The days come from
 * a count that has no limit. An event's IP address is never asked for.
 *
 * @phpstan-import-type DayCount from SecurityReport
 * @phpstan-import-type PeopleBlock from SecurityReport
 */
final readonly class SecurityReader
{
    private const string ZONE_QUERY = <<<'GRAPHQL'
        query ($zoneTag: string, $sinceDate: Date!, $untilDate: Date!, $since: Time!, $until: Time!) {
          viewer {
            zones(filter: {zoneTag: $zoneTag}) {
              days: httpRequests1dGroups(limit: 40, filter: {date_geq: $sinceDate, date_leq: $untilDate}, orderBy: [date_ASC]) {
                dimensions { date }
                sum { threats countryMap { clientCountryName threats } }
              }
              blocks: firewallEventsAdaptiveByTimeGroups(limit: 40, filter: {datetime_geq: $since, datetime_leq: $until}, orderBy: [date_ASC]) {
                count
                dimensions { date }
              }
              events: firewallEventsAdaptive(limit: 10000, filter: {datetime_geq: $since, datetime_leq: $until}, orderBy: [datetime_DESC]) {
                action
                source
                clientCountryName
                clientRequestPath
                clientRequestHTTPMethodName
                userAgent
                ruleId
                description
              }
            }
          }
        }
        GRAPHQL;

    private const string TURNSTILE_QUERY = <<<'GRAPHQL'
        query ($accountTag: string, $filter: AccountTurnstileAdaptiveGroupsFilter_InputObject) {
          viewer {
            accounts(filter: {accountTag: $accountTag}) {
              turnstile: turnstileAdaptiveGroups(limit: 50, filter: $filter, orderBy: [count_DESC]) {
                count
                dimensions { eventType }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private string $zoneId,
        private ?string $accountId,
        private ?string $turnstileSiteKey,
    ) {}

    public function read(DateTimeImmutable $now): SecurityReport
    {
        $since = $now->setTime(0, 0)->modify(sprintf('-%d days', SecurityReport::DAYS - 1));
        $zone = $this->cloudflareGraphql->ask(self::ZONE_QUERY, [
            'zoneTag' => $this->zoneId,
            'sinceDate' => CloudflareGraphql::date($since),
            'untilDate' => CloudflareGraphql::date($now),
            'since' => CloudflareGraphql::time($since),
            'until' => CloudflareGraphql::time($now),
        ], 'zones');

        $threatDays = [];
        $threatCountries = new Tally;

        foreach (CloudflareGraphql::rows($zone, 'days') as $row) {
            $date = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'date'));
            $countries = CloudflareGraphql::value($row, 'sum', 'countryMap');

            if ($date === '') {
                continue;
            }

            $threatDays[] = ['date' => $date, 'count' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'threats'))];

            foreach (is_array($countries) ? array_filter($countries, is_array(...)) : [] as $country) {
                $threatCountries->add(CloudflareGraphql::text($country['clientCountryName'] ?? null), CloudflareGraphql::int($country['threats'] ?? null));
            }
        }

        $events = CloudflareGraphql::rows($zone, 'events');
        $sources = new Tally;
        $actions = new Tally;
        $countries = new Tally;
        $paths = new Tally;
        $userAgents = new Tally;
        $rules = new Tally;

        foreach ($events as $event) {
            $path = CloudflareGraphql::text($event['clientRequestPath'] ?? null);
            $sources->add(CloudflareGraphql::text($event['source'] ?? null), 1);
            $actions->add(CloudflareGraphql::text($event['action'] ?? null), 1);
            $countries->add(CloudflareGraphql::text($event['clientCountryName'] ?? null), 1);
            $paths->add($path, 1);
            $userAgent = CloudflareGraphql::text($event['userAgent'] ?? null);
            $userAgents->add($userAgent === '' ? '(none)' : $userAgent, 1);
            $rules->add(CloudflareGraphql::text($event['ruleId'] ?? null), 1);
        }

        $peopleBlocks = $this->peopleBlocks($events);

        return new SecurityReport(
            threatDays: $threatDays,
            threatCountries: $threatCountries->ranked(),
            blockDays: $this->blockDays($zone),
            events: count($events),
            sources: $sources->ranked(),
            actions: $actions->ranked(),
            countries: $countries->ranked(),
            paths: $paths->ranked(15),
            userAgents: $userAgents->ranked(10),
            rules: $rules->ranked(10),
            peoplePages: array_slice($peopleBlocks, 0, 15),
            peopleBlocked: array_sum(array_column($peopleBlocks, 'count')),
            turnstile: $this->turnstile($now),
        );
    }

    /**
     * The events on pages people use, added up by path, method, and rule, most first. A rule is named by its
     * description, such as a managed rule's CVE, or by its id when it has none.
     *
     * @param  list<array<mixed>>  $events
     * @return list<PeopleBlock>
     */
    private function peopleBlocks(array $events): array
    {
        /** @var array<string, PeopleBlock> $blocks */
        $blocks = [];

        foreach ($events as $event) {
            $path = CloudflareGraphql::text($event['clientRequestPath'] ?? null);
            $method = mb_strtoupper(CloudflareGraphql::text($event['clientRequestHTTPMethodName'] ?? null));

            if (! PeoplePages::is($path, $method)) {
                continue;
            }

            $description = CloudflareGraphql::text($event['description'] ?? null);
            $rule = $description === '' ? CloudflareGraphql::text($event['ruleId'] ?? null) : $description;
            $source = CloudflareGraphql::text($event['source'] ?? null);
            $key = implode("\n", [$path, $method, $rule, $source]);
            $blocks[$key] ??= ['path' => $path, 'method' => $method, 'rule' => $rule, 'source' => $source, 'count' => 0];
            $blocks[$key]['count']++;
        }

        $ranked = array_values($blocks);
        usort($ranked, static fn (array $a, array $b): int => [$b['count'], $a['path'], $a['method']] <=> [$a['count'], $b['path'], $b['method']]);

        return $ranked;
    }

    /**
     * @param  array<mixed>  $zone
     * @return list<DayCount>
     */
    private function blockDays(array $zone): array
    {
        $days = [];

        foreach (CloudflareGraphql::rows($zone, 'blocks') as $row) {
            $date = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'date'));

            if ($date !== '') {
                $days[] = ['date' => $date, 'count' => CloudflareGraphql::int($row['count'] ?? null)];
            }
        }

        return $days;
    }

    /**
     * The sign-up checks of the last 7 days, by what happened. Without an account id there is nothing to ask.
     *
     * @return list<array{label: string, count: int}>
     */
    private function turnstile(DateTimeImmutable $now): array
    {
        if ($this->accountId === null) {
            return [];
        }

        $filter = [
            'datetime_geq' => CloudflareGraphql::time($now->modify(sprintf('-%d days', SecurityReport::TURNSTILE_DAYS))->modify('+1 minute')),
            'datetime_leq' => CloudflareGraphql::time($now),
        ];

        if ($this->turnstileSiteKey !== null) {
            $filter['siteKey'] = $this->turnstileSiteKey;
        }

        $account = $this->cloudflareGraphql->ask(self::TURNSTILE_QUERY, ['accountTag' => $this->accountId, 'filter' => $filter], 'accounts');
        $tally = new Tally;

        foreach (CloudflareGraphql::rows($account, 'turnstile') as $row) {
            $tally->add(CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'eventType')), CloudflareGraphql::int($row['count'] ?? null));
        }

        return $tally->ranked();
    }
}
