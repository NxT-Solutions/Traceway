<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Insights;

use App\Domain\Traffic\BotNames;
use App\Domain\Traffic\BotTraffic;
use App\Domain\Traffic\SecretProbes;
use App\Integrations\Cloudflare\CloudflareGraphql;
use App\Integrations\Cloudflare\Tally;
use DateTimeImmutable;

/**
 * Single requests of the last 30 days, grouped. The free plan keeps them for about a month and refuses
 * `clientAsn`. Each headline total has a query of its own with no dimensions, so a row limit cannot lower it.
 *
 * @phpstan-import-type Read from BotTraffic
 * @phpstan-import-type StatusCount from BotTraffic
 */
final readonly class BotsReader
{
    private const string QUERY = <<<'GRAPHQL'
        query ($zoneTag: string, $verified: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject, $reads: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject, $impostors: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject, $blocked: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject, $probes: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject, $exposed: ZoneHttpRequestsAdaptiveGroupsFilter_InputObject) {
          viewer {
            zones(filter: {zoneTag: $zoneTag}) {
              verified: httpRequestsAdaptiveGroups(limit: 50, filter: $verified, orderBy: [count_DESC]) {
                count
                dimensions { verifiedBotCategory }
              }
              reads: httpRequestsAdaptiveGroups(limit: 500, filter: $reads, orderBy: [count_DESC]) {
                count
                dimensions { clientRequestPath clientRequestHTTPMethodName edgeResponseStatus }
              }
              readsTotal: httpRequestsAdaptiveGroups(limit: 1, filter: $reads) { count }
              readers: httpRequestsAdaptiveGroups(limit: 2000, filter: $reads, orderBy: [count_DESC]) {
                count
                dimensions { verifiedBotCategory userAgent }
              }
              impostors: httpRequestsAdaptiveGroups(limit: 2000, filter: $impostors, orderBy: [count_DESC]) {
                count
                dimensions { userAgent }
              }
              impostorsTotal: httpRequestsAdaptiveGroups(limit: 1, filter: $impostors) { count }
              impostorsBlocked: httpRequestsAdaptiveGroups(limit: 1, filter: $blocked) { count }
              probes: httpRequestsAdaptiveGroups(limit: 50, filter: $probes, orderBy: [count_DESC]) {
                count
                dimensions { edgeResponseStatus }
              }
              exposed: httpRequestsAdaptiveGroups(limit: 50, filter: $exposed, orderBy: [count_DESC]) {
                count
                dimensions { clientRequestPath }
              }
            }
          }
        }
        GRAPHQL;

    /** What Traceway publishes for AI assistants and crawlers, besides the pages ending in `.md`. */
    private const array PUBLISHED_PATHS = ['/llms.txt', '/llms-full.txt', '/robots.txt', '/mcp'];

    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private string $zoneId,
    ) {}

    public function read(DateTimeImmutable $now): BotTraffic
    {
        $window = [
            'datetime_geq' => CloudflareGraphql::time($now->setTime(0, 0)->modify(sprintf('-%d days', BotTraffic::DAYS - 1))),
            'datetime_leq' => CloudflareGraphql::time($now),
        ];
        $impostors = $window + [
            'verifiedBotCategory' => '',
            'OR' => array_map(static fn (string $pattern): array => ['userAgent_like' => $pattern], BotNames::likePatterns()),
        ];
        $probes = $window + [
            'clientRequestPath_neq' => SecretProbes::PUBLIC_FILE,
            'OR' => array_map(static fn (string $pattern): array => ['clientRequestPath_like' => $pattern], SecretProbes::PATTERNS),
        ];

        $zone = $this->cloudflareGraphql->ask(self::QUERY, [
            'zoneTag' => $this->zoneId,
            'verified' => $window + ['verifiedBotCategory_neq' => ''],
            'reads' => $window + ['OR' => [
                ['clientRequestPath_in' => self::PUBLISHED_PATHS],
                ['clientRequestPath_like' => '%.md'],
                ['clientRequestPath_like' => '/.well-known/oauth-%'],
            ]],
            'impostors' => $impostors,
            'blocked' => $impostors + ['edgeResponseStatus' => 403],
            'probes' => $probes,
            'exposed' => $probes + ['edgeResponseStatus_geq' => 200, 'edgeResponseStatus_lt' => 300],
        ], 'zones');

        return new BotTraffic(
            verified: $this->tally($zone, 'verified', 'verifiedBotCategory')->ranked(),
            reads: $this->reads($zone),
            readsTotal: $this->total($zone, 'readsTotal'),
            readers: $this->readers($zone),
            impostors: $this->impostors($zone),
            impostorsTotal: $this->total($zone, 'impostorsTotal'),
            impostorsBlocked: $this->total($zone, 'impostorsBlocked'),
            probes: $this->statuses($zone),
            exposed: $this->tally($zone, 'exposed', 'clientRequestPath')->ranked(),
        );
    }

    /**
     * @param  array<mixed>  $zone
     * @return list<Read>
     */
    private function reads(array $zone): array
    {
        $reads = [];

        foreach (CloudflareGraphql::rows($zone, 'reads') as $row) {
            $path = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'clientRequestPath'));
            $method = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'clientRequestHTTPMethodName'));

            if ($path !== '' && $method !== '') {
                $reads[] = [
                    'path' => $path,
                    'method' => $method,
                    'status' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'dimensions', 'edgeResponseStatus')),
                    'count' => CloudflareGraphql::int($row['count'] ?? null),
                ];
            }
        }

        return $reads;
    }

    /**
     * Who read what Traceway publishes: the kind Cloudflare verified, the bot a user agent claims to be, or other
     * clients such as a browser or curl.
     *
     * @param  array<mixed>  $zone
     * @return list<array{label: string, count: int}>
     */
    private function readers(array $zone): array
    {
        $tally = new Tally;

        foreach (CloudflareGraphql::rows($zone, 'readers') as $row) {
            $category = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'verifiedBotCategory'));
            $claimed = BotNames::claimed(CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'userAgent')));

            $tally->add(match (true) {
                $category !== '' => $category,
                $claimed !== null => $claimed.', unverified',
                default => 'Other clients',
            }, CloudflareGraphql::int($row['count'] ?? null));
        }

        return $tally->ranked();
    }

    /**
     * @param  array<mixed>  $zone
     * @return list<array{label: string, count: int}>
     */
    private function impostors(array $zone): array
    {
        $tally = new Tally;

        foreach (CloudflareGraphql::rows($zone, 'impostors') as $row) {
            $claimed = BotNames::claimed(CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'userAgent')));

            if ($claimed !== null) {
                $tally->add($claimed, CloudflareGraphql::int($row['count'] ?? null));
            }
        }

        return $tally->ranked();
    }

    /**
     * @param  array<mixed>  $zone
     * @return list<StatusCount>
     */
    private function statuses(array $zone): array
    {
        return array_map(static fn (array $row): array => [
            'status' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'dimensions', 'edgeResponseStatus')),
            'count' => CloudflareGraphql::int($row['count'] ?? null),
        ], CloudflareGraphql::rows($zone, 'probes'));
    }

    /**
     * @param  array<mixed>  $zone
     */
    private function tally(array $zone, string $alias, string $dimension): Tally
    {
        $tally = new Tally;

        foreach (CloudflareGraphql::rows($zone, $alias) as $row) {
            $tally->add(CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', $dimension)), CloudflareGraphql::int($row['count'] ?? null));
        }

        return $tally;
    }

    /**
     * @param  array<mixed>  $zone
     */
    private function total(array $zone, string $alias): int
    {
        return CloudflareGraphql::int(CloudflareGraphql::rows($zone, $alias)[0]['count'] ?? null);
    }
}
