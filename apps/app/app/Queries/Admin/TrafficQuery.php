<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * The admin area's Traffic page, from Cloudflare's totals. An answer is kept for an hour, and a failed answer for
 * five minutes, so the page never waits on Cloudflare twice in a row.
 *
 * @phpstan-import-type RequestDay from TrafficReport
 * @phpstan-import-type VisitDay from TrafficReport
 * @phpstan-import-type Ranked from TrafficReport
 *
 * @phpstan-type Totals array{requests: int, cached: int, bytes: int, page_views: int, threats: int, page_loads: int, visits: int}
 * @phpstan-type Page array{connected: bool, error: string|null, days: int, generated_at: string, totals: Totals, request_days: list<RequestDay>, visit_days: list<VisitDay>, top_pages: list<Ranked>, top_referrers: list<Ranked>, countries: list<Ranked>}
 */
final readonly class TrafficQuery
{
    /** Cloudflare keeps a month of daily totals on every plan. */
    public const int DAYS = 30;

    public function __construct(
        private TrafficSource $trafficSource,
        private CacheRepository $cacheRepository,
    ) {}

    /**
     * @return Page
     */
    public function page(): array
    {
        if (! $this->trafficSource->connected()) {
            return $this->shape(connected: false, error: null, trafficReport: new TrafficReport([], [], [], [], []));
        }

        $key = 'admin-traffic:'.CarbonImmutable::now()->format('Y-m-d-H');
        $cached = $this->cacheRepository->get($key);

        if (is_array($cached)) {
            /** @var Page $cached */
            return $cached;
        }

        $until = CarbonImmutable::now();

        try {
            $page = $this->shape(connected: true, error: null, trafficReport: $this->trafficSource->report($until->subDays(self::DAYS - 1)->startOfDay()->toDateTimeImmutable(), $until->toDateTimeImmutable()));
            $this->cacheRepository->put($key, $page, 3600);
        } catch (TrafficUnavailable $trafficUnavailable) {
            $page = $this->shape(connected: true, error: $trafficUnavailable->getMessage(), trafficReport: new TrafficReport([], [], [], [], []));
            $this->cacheRepository->put($key, $page, 300);
        }

        return $page;
    }

    /**
     * @return Page
     */
    private function shape(bool $connected, ?string $error, TrafficReport $trafficReport): array
    {
        return [
            'connected' => $connected,
            'error' => $error,
            'days' => self::DAYS,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'totals' => [
                'requests' => array_sum(array_column($trafficReport->requestDays, 'requests')),
                'cached' => array_sum(array_column($trafficReport->requestDays, 'cached')),
                'bytes' => array_sum(array_column($trafficReport->requestDays, 'bytes')),
                'page_views' => array_sum(array_column($trafficReport->requestDays, 'page_views')),
                'threats' => array_sum(array_column($trafficReport->requestDays, 'threats')),
                'page_loads' => array_sum(array_column($trafficReport->visitDays, 'page_loads')),
                'visits' => array_sum(array_column($trafficReport->visitDays, 'visits')),
            ],
            'request_days' => $trafficReport->requestDays,
            'visit_days' => $trafficReport->visitDays,
            'top_pages' => $trafficReport->topPages,
            'top_referrers' => $trafficReport->topReferrers,
            'countries' => $trafficReport->countries,
        ];
    }
}
