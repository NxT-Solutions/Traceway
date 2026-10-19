<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Insights;

use App\Domain\Traffic\RequestBreakdown;
use App\Integrations\Cloudflare\CloudflareGraphql;
use App\Integrations\Cloudflare\Tally;
use DateTimeImmutable;

/**
 * A year of daily totals with their maps, and the hours of the last three days. The maps of the last 30 days are
 * added up into the kinds of request.
 *
 * @phpstan-import-type Hour from RequestBreakdown
 * @phpstan-import-type Day from RequestBreakdown
 */
final readonly class RequestsReader
{
    private const string QUERY = <<<'GRAPHQL'
        query ($zoneTag: string, $sinceDate: Date!, $untilDate: Date!, $sinceHour: Time!, $until: Time!) {
          viewer {
            zones(filter: {zoneTag: $zoneTag}) {
              days: httpRequests1dGroups(limit: 400, filter: {date_geq: $sinceDate, date_leq: $untilDate}, orderBy: [date_ASC]) {
                dimensions { date }
                sum {
                  requests
                  threats
                  browserMap { uaBrowserFamily pageViews }
                  responseStatusMap { edgeResponseStatus requests }
                  contentTypeMap { edgeResponseContentTypeName requests }
                  clientHTTPVersionMap { clientHTTPProtocol requests }
                  ipClassMap { ipType requests }
                }
              }
              hours: httpRequests1hGroups(limit: 100, filter: {datetime_geq: $sinceHour, datetime_leq: $until}, orderBy: [datetime_ASC]) {
                dimensions { datetime }
                sum { requests threats }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private string $zoneId,
    ) {}

    public function read(DateTimeImmutable $now): RequestBreakdown
    {
        // An hour short of three days, from the top of the hour, stays inside the hourly groups' limit.
        $hour = $now->setTime((int) $now->format('G'), 0);
        $zone = $this->cloudflareGraphql->ask(self::QUERY, [
            'zoneTag' => $this->zoneId,
            'sinceDate' => CloudflareGraphql::date($now->modify(sprintf('-%d days', RequestBreakdown::YEAR_DAYS - 1))),
            'untilDate' => CloudflareGraphql::date($now),
            'sinceHour' => CloudflareGraphql::time($hour->modify(sprintf('-%d hours', RequestBreakdown::HOURS - 1))),
            'until' => CloudflareGraphql::time($now),
        ], 'zones');

        $recent = CloudflareGraphql::date($now->modify(sprintf('-%d days', RequestBreakdown::DAYS - 1)));
        $days = [];
        $statuses = [];
        $contentTypes = new Tally;
        $browsers = new Tally;
        $httpVersions = new Tally;
        $ipClasses = new Tally;

        foreach (CloudflareGraphql::rows($zone, 'days') as $row) {
            $date = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'date'));

            if ($date === '') {
                continue;
            }

            $days[] = [
                'date' => $date,
                'requests' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'requests')),
                'threats' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'threats')),
            ];

            if ($date < $recent) {
                continue;
            }

            foreach ($this->map($row, 'responseStatusMap') as $entry) {
                $status = CloudflareGraphql::int($entry['edgeResponseStatus'] ?? null);
                $statuses[$status] = ($statuses[$status] ?? 0) + CloudflareGraphql::int($entry['requests'] ?? null);
            }

            $this->add($contentTypes, $this->map($row, 'contentTypeMap'), 'edgeResponseContentTypeName', 'requests');
            $this->add($browsers, $this->map($row, 'browserMap'), 'uaBrowserFamily', 'pageViews');
            $this->add($httpVersions, $this->map($row, 'clientHTTPVersionMap'), 'clientHTTPProtocol', 'requests');
            $this->add($ipClasses, $this->map($row, 'ipClassMap'), 'ipType', 'requests');
        }

        arsort($statuses);

        return new RequestBreakdown(
            hours: $this->hours($zone),
            days: $days,
            statuses: array_map(static fn (int $status, int $count): array => ['status' => $status, 'count' => $count], array_keys($statuses), array_values($statuses)),
            contentTypes: $contentTypes->ranked(10),
            browsers: $browsers->ranked(10),
            httpVersions: $httpVersions->ranked(),
            ipClasses: $ipClasses->ranked(),
        );
    }

    /**
     * @param  array<mixed>  $zone
     * @return list<Hour>
     */
    private function hours(array $zone): array
    {
        $hours = [];

        foreach (CloudflareGraphql::rows($zone, 'hours') as $row) {
            $time = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'datetime'));

            if ($time !== '') {
                $hours[] = [
                    'time' => $time,
                    'requests' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'requests')),
                    'threats' => CloudflareGraphql::int(CloudflareGraphql::value($row, 'sum', 'threats')),
                ];
            }
        }

        return $hours;
    }

    /**
     * @param  array<mixed>  $row
     * @return list<array<mixed>>
     */
    private function map(array $row, string $name): array
    {
        $map = CloudflareGraphql::value($row, 'sum', $name);

        return is_array($map) ? array_values(array_filter($map, is_array(...))) : [];
    }

    /**
     * @param  list<array<mixed>>  $entries
     */
    private function add(Tally $tally, array $entries, string $label, string $count): void
    {
        foreach ($entries as $entry) {
            $tally->add(CloudflareGraphql::text($entry[$label] ?? null), CloudflareGraphql::int($entry[$count] ?? null));
        }
    }
}
