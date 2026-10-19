<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * The traffic of a stretch of days. Requests cover everything that reached traceway.online through Cloudflare, and
 * never say how many people sent them. Visits cover only the pages of the site, counted after the visitor said yes.
 *
 * @phpstan-type RequestDay array{date: string, requests: int, cached: int, bytes: int, threats: int}
 * @phpstan-type VisitDay array{date: string, page_loads: int, visits: int}
 * @phpstan-type Ranked array{label: string, count: int}
 */
final readonly class TrafficReport
{
    /** How many pages, referrers, and countries the Traffic page lists. */
    public const int RANKED_LIMIT = 10;

    /**
     * @param  list<RequestDay>  $requestDays
     * @param  list<VisitDay>  $visitDays
     * @param  list<Ranked>  $topPages
     * @param  list<Ranked>  $topReferrers
     * @param  list<Ranked>  $countries
     */
    public function __construct(
        public array $requestDays,
        public array $visitDays,
        public array $topPages,
        public array $topReferrers,
        public array $countries,
    ) {}

    /**
     * Whether the visits half of the answer holds together. Cloudflare sometimes leaves one of its four lists empty
     * with no error, or answers coarse with page loads and no visits. Page loads always come with a day, a visit, a
     * page, a referrer, and a country. An answer with none of them is a stretch with no page loads.
     */
    public function visitsComplete(): bool
    {
        if ($this->visitsEmpty()) {
            return true;
        }

        return array_sum(array_column($this->visitDays, 'page_loads')) > 0
            && array_sum(array_column($this->visitDays, 'visits')) > 0
            && $this->topPages !== []
            && $this->topReferrers !== []
            && $this->countries !== [];
    }

    /**
     * Whether the visits half of the answer holds nothing at all: no day, no page, no referrer, and no country.
     */
    public function visitsEmpty(): bool
    {
        return $this->visitDays === [] && $this->topPages === [] && $this->topReferrers === [] && $this->countries === [];
    }

    /**
     * This report with the kept days before it. A day the report already holds is not added again. Each kept day
     * holds its own top ten, so a page that never made a day's top ten is not counted for that day.
     *
     * @param  list<TrafficDay>  $trafficDays
     */
    public function withDays(array $trafficDays): self
    {
        $visitDays = [];

        foreach ($trafficDays as $trafficDay) {
            $visitDays[$trafficDay->date] = $trafficDay->visitDay();
        }

        foreach ($this->visitDays as $visitDay) {
            $visitDays[$visitDay['date']] = $visitDay;
        }

        ksort($visitDays);
        $live = array_column($this->visitDays, 'date');
        $kept = array_values(array_filter($trafficDays, fn (TrafficDay $trafficDay): bool => ! in_array($trafficDay->date, $live, strict: true)));

        return new self(
            $this->requestDays,
            array_values($visitDays),
            $this->top([$this->topPages, ...array_map(fn (TrafficDay $trafficDay): array => $trafficDay->topPages, $kept)]),
            $this->top([$this->topReferrers, ...array_map(fn (TrafficDay $trafficDay): array => $trafficDay->topReferrers, $kept)]),
            $this->top([$this->countries, ...array_map(fn (TrafficDay $trafficDay): array => $trafficDay->countries, $kept)]),
        );
    }

    /**
     * @param  list<list<Ranked>>  $lists
     * @return list<Ranked>
     */
    private function top(array $lists): array
    {
        $totals = [];

        foreach ($lists as $list) {
            foreach ($list as $row) {
                $totals[$row['label']] = ['label' => $row['label'], 'count' => ($totals[$row['label']]['count'] ?? 0) + $row['count']];
            }
        }

        $top = array_values($totals);
        usort($top, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($top, 0, self::RANKED_LIMIT);
    }
}
