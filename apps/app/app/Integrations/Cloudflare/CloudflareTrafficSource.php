<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use App\Integrations\Cloudflare\Requests\AnalyticsQueryRequest;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Reads the zone's daily request totals and the Web Analytics page loads of the site. Either half is left empty
 * when its id is not set.
 *
 * @phpstan-import-type RequestDay from TrafficReport
 * @phpstan-import-type VisitDay from TrafficReport
 * @phpstan-import-type Ranked from TrafficReport
 */
final readonly class CloudflareTrafficSource implements TrafficSource
{
    /** How far back a stretch can start for Web Analytics to answer it in full. */
    private const int VISITS_WINDOW_DAYS = 7;

    private const int RANKED_LIMIT = 10;

    private const string ZONE_QUERY = <<<'GRAPHQL'
        query ($zoneTag: string, $since: Date!, $until: Date!) {
          viewer {
            zones(filter: {zoneTag: $zoneTag}) {
              httpRequests1dGroups(limit: 400, filter: {date_geq: $since, date_leq: $until}, orderBy: [date_ASC]) {
                dimensions { date }
                sum { requests cachedRequests bytes threats }
              }
            }
          }
        }
        GRAPHQL;

    private const string VISITS_QUERY = <<<'GRAPHQL'
        query ($accountTag: string, $filter: AccountRumPageloadEventsAdaptiveGroupsFilter_InputObject) {
          viewer {
            accounts(filter: {accountTag: $accountTag}) {
              days: rumPageloadEventsAdaptiveGroups(limit: 400, filter: $filter, orderBy: [date_ASC]) {
                count
                sum { visits }
                dimensions { date }
              }
              pages: rumPageloadEventsAdaptiveGroups(limit: 10, filter: $filter, orderBy: [count_DESC]) {
                count
                dimensions { requestPath }
              }
              referrers: rumPageloadEventsAdaptiveGroups(limit: 10, filter: $filter, orderBy: [count_DESC]) {
                count
                dimensions { refererHost }
              }
              countries: rumPageloadEventsAdaptiveGroups(limit: 10, filter: $filter, orderBy: [count_DESC]) {
                count
                dimensions { countryName }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private CloudflareAnalyticsConnector $cloudflareAnalyticsConnector,
        private ?string $zoneId,
        private ?string $accountId,
        private ?string $siteTag,
    ) {}

    public function connected(): bool
    {
        return true;
    }

    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        $requestDays = [];

        if ($this->zoneId !== null) {
            $zone = $this->ask(self::ZONE_QUERY, [
                'zoneTag' => $this->zoneId,
                'since' => $since->format('Y-m-d'),
                'until' => $until->format('Y-m-d'),
            ]);
            $requestDays = $this->requestDays($this->first($zone, 'zones')['httpRequests1dGroups'] ?? []);
        }

        $visitDays = [];
        $topPages = [];
        $topReferrers = [];
        $countries = [];

        if ($this->accountId !== null && $this->siteTag !== null) {
            foreach ($this->windows($since, $until) as [$from, $to]) {
                $account = $this->first($this->ask(self::VISITS_QUERY, [
                    'accountTag' => $this->accountId,
                    'filter' => [
                        'AND' => [
                            ['siteTag' => $this->siteTag],
                            ['datetime_geq' => $from->format('Y-m-d\TH:i:s\Z')],
                            ['datetime_leq' => $to->format('Y-m-d\TH:i:s\Z')],
                        ],
                    ],
                ]), 'accounts');
                $visitDays = [...$visitDays, ...$this->visitDays($account['days'] ?? [])];
                $topPages = [...$topPages, ...$this->ranked($account['pages'] ?? [], 'requestPath')];
                $topReferrers = [...$topReferrers, ...$this->ranked($account['referrers'] ?? [], 'refererHost')];
                $countries = [...$countries, ...$this->ranked($account['countries'] ?? [], 'countryName')];
            }

            $visitDays = $this->mergedDays($visitDays);
            $topPages = $this->top($topPages);
            $topReferrers = $this->top($topReferrers);
            $countries = $this->top($countries);
        }

        return new TrafficReport($requestDays, $visitDays, $topPages, $topReferrers, $countries);
    }

    /**
     * Web Analytics answers in full only for a stretch that starts less than a week ago. One that starts earlier
     * comes back from a coarser store, with recent days missing and no visits, and no error. So the last seven days
     * are asked on their own, from midnight UTC, and the weeks before them one at a time, oldest first.
     *
     * @return list<array{DateTimeImmutable, DateTimeImmutable}>
     */
    private function windows(DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        $dateTimeZone = new DateTimeZone('UTC');
        $week = new DateInterval('P'.self::VISITS_WINDOW_DAYS.'D');
        $second = new DateInterval('PT1S');
        $since = $since->setTimezone($dateTimeZone);
        $to = $until->setTimezone($dateTimeZone);
        $from = $to->setTime(0, 0)->sub(new DateInterval('P'.(self::VISITS_WINDOW_DAYS - 1).'D'));
        $windows = [];

        while ($to >= $since) {
            $windows[] = [max($from, $since), $to];
            $to = $from->sub($second);
            $from = $from->sub($week);
        }

        return array_reverse($windows);
    }

    /**
     * @param  list<VisitDay>  $days
     * @return list<VisitDay>
     */
    private function mergedDays(array $days): array
    {
        $merged = [];

        foreach ($days as $day) {
            $merged[$day['date']] = [
                'date' => $day['date'],
                'page_loads' => ($merged[$day['date']]['page_loads'] ?? 0) + $day['page_loads'],
                'visits' => ($merged[$day['date']]['visits'] ?? 0) + $day['visits'],
            ];
        }

        return array_values($merged);
    }

    /**
     * Sums each window's top ten by label. A label that never made a window's top ten is not counted.
     *
     * @param  list<Ranked>  $ranked
     * @return list<Ranked>
     */
    private function top(array $ranked): array
    {
        $totals = [];

        foreach ($ranked as $row) {
            $totals[$row['label']] = [
                'label' => $row['label'],
                'count' => ($totals[$row['label']]['count'] ?? 0) + $row['count'],
            ];
        }

        $top = array_values($totals);
        usort($top, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($top, 0, self::RANKED_LIMIT);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<mixed>
     */
    private function ask(string $query, array $variables): array
    {
        try {
            $response = $this->cloudflareAnalyticsConnector->send(new AnalyticsQueryRequest($query, $variables));
        } catch (FatalRequestException $fatalRequestException) {
            throw new TrafficUnavailable('Cloudflare did not answer.', 0, $fatalRequestException);
        }

        throw_if($response->failed(), TrafficUnavailable::class, 'Cloudflare refused the question. Check the token can read analytics.');

        try {
            $body = $response->json();
        } catch (JsonException $jsonException) {
            throw new TrafficUnavailable('Cloudflare answered with something other than numbers.', 0, $jsonException);
        }

        throw_if(is_array($body['errors'] ?? null) && $body['errors'] !== [], TrafficUnavailable::class, 'Cloudflare refused the question. Check the token can read analytics.');

        $viewer = is_array($body['data'] ?? null) ? ($body['data']['viewer'] ?? null) : null;

        return is_array($viewer) ? $viewer : [];
    }

    /**
     * @param  array<mixed>  $viewer
     * @return array<mixed>
     */
    private function first(array $viewer, string $key): array
    {
        $list = $viewer[$key] ?? null;
        $first = is_array($list) ? ($list[0] ?? null) : null;

        return is_array($first) ? $first : [];
    }

    /**
     * @return list<RequestDay>
     */
    private function requestDays(mixed $groups): array
    {
        $days = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $date = $this->path($group, 'dimensions', 'date');

            if (! is_string($date)) {
                continue;
            }

            $days[] = [
                'date' => $date,
                'requests' => $this->int($this->path($group, 'sum', 'requests')),
                'cached' => $this->int($this->path($group, 'sum', 'cachedRequests')),
                'bytes' => $this->int($this->path($group, 'sum', 'bytes')),
                'threats' => $this->int($this->path($group, 'sum', 'threats')),
            ];
        }

        return $days;
    }

    /**
     * @return list<VisitDay>
     */
    private function visitDays(mixed $groups): array
    {
        $days = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $date = $this->path($group, 'dimensions', 'date');

            if (! is_string($date)) {
                continue;
            }

            $days[] = [
                'date' => $date,
                'page_loads' => $this->int(is_array($group) ? ($group['count'] ?? null) : null),
                'visits' => $this->int($this->path($group, 'sum', 'visits')),
            ];
        }

        return $days;
    }

    /**
     * @return list<Ranked>
     */
    private function ranked(mixed $groups, string $dimension): array
    {
        $ranked = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $label = $this->path($group, 'dimensions', $dimension);

            $ranked[] = [
                'label' => is_string($label) && $label !== '' ? $label : '(none)',
                'count' => $this->int(is_array($group) ? ($group['count'] ?? null) : null),
            ];
        }

        return $ranked;
    }

    private function path(mixed $group, string $outer, string $inner): mixed
    {
        if (! is_array($group) || ! is_array($group[$outer] ?? null)) {
            return null;
        }

        return $group[$outer][$inner] ?? null;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
