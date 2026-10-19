<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Insights;

use App\Domain\Traffic\SpeedReport;
use App\Integrations\Cloudflare\CloudflareGraphql;
use DateTimeImmutable;

/**
 * Core Web Vitals and load times of the site's pages, from Web Analytics, at the 75th percentile. Cloudflare gives
 * times in microseconds and -1 for nothing measured, such as INP on a page nobody tapped.
 *
 * @phpstan-import-type PageVitals from SpeedReport
 * @phpstan-import-type PageTimings from SpeedReport
 */
final readonly class SpeedReader
{
    private const string QUERY = <<<'GRAPHQL'
        query ($accountTag: string, $filter: AccountRumWebVitalsEventsAdaptiveGroupsFilter_InputObject, $timings: AccountRumPerformanceEventsAdaptiveGroupsFilter_InputObject) {
          viewer {
            accounts(filter: {accountTag: $accountTag}) {
              vitals: rumWebVitalsEventsAdaptiveGroups(limit: 20, filter: $filter, orderBy: [count_DESC]) {
                count
                quantiles { largestContentfulPaintP75 interactionToNextPaintP75 cumulativeLayoutShiftP75 }
                dimensions { requestPath }
              }
              timings: rumPerformanceEventsAdaptiveGroups(limit: 20, filter: $timings, orderBy: [count_DESC]) {
                count
                quantiles { pageLoadTimeP75 firstContentfulPaintP75 }
                dimensions { requestPath }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private string $accountId,
        private string $siteTag,
    ) {}

    public function read(DateTimeImmutable $now): SpeedReport
    {
        // Web Analytics answers in full only for a stretch that starts less than a week ago.
        $filter = ['AND' => [
            ['siteTag' => $this->siteTag],
            ['datetime_geq' => CloudflareGraphql::time($now->setTime(0, 0)->modify(sprintf('-%d days', SpeedReport::DAYS - 1)))],
            ['datetime_leq' => CloudflareGraphql::time($now)],
        ]];
        $account = $this->cloudflareGraphql->ask(self::QUERY, [
            'accountTag' => $this->accountId,
            'filter' => $filter,
            'timings' => $filter,
        ], 'accounts');

        $vitals = [];

        foreach (CloudflareGraphql::rows($account, 'vitals') as $row) {
            $vitals[] = [
                'path' => $this->path($row),
                'loads' => CloudflareGraphql::int($row['count'] ?? null),
                'lcp_ms' => $this->milliseconds(CloudflareGraphql::value($row, 'quantiles', 'largestContentfulPaintP75')),
                'inp_ms' => $this->milliseconds(CloudflareGraphql::value($row, 'quantiles', 'interactionToNextPaintP75')),
                'cls' => $this->shift(CloudflareGraphql::value($row, 'quantiles', 'cumulativeLayoutShiftP75')),
            ];
        }

        $timings = [];

        foreach (CloudflareGraphql::rows($account, 'timings') as $row) {
            $timings[] = [
                'path' => $this->path($row),
                'loads' => CloudflareGraphql::int($row['count'] ?? null),
                'load_ms' => $this->milliseconds(CloudflareGraphql::value($row, 'quantiles', 'pageLoadTimeP75')),
                'first_paint_ms' => $this->milliseconds(CloudflareGraphql::value($row, 'quantiles', 'firstContentfulPaintP75')),
            ];
        }

        return new SpeedReport($vitals, $timings);
    }

    /**
     * @param  array<mixed>  $row
     */
    private function path(array $row): string
    {
        $path = CloudflareGraphql::text(CloudflareGraphql::value($row, 'dimensions', 'requestPath'));

        return $path === '' ? '(none)' : $path;
    }

    /**
     * Microseconds as milliseconds. Zero or less means nothing was measured.
     */
    private function milliseconds(mixed $microseconds): ?float
    {
        return is_numeric($microseconds) && $microseconds > 0 ? round((float) $microseconds / 1000, 1) : null;
    }

    /**
     * A layout shift has no unit, and zero is the best score. Below zero means nothing was measured.
     */
    private function shift(mixed $value): ?float
    {
        return is_numeric($value) && $value >= 0 ? round((float) $value, 3) : null;
    }
}
