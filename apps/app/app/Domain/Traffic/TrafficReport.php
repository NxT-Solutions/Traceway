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
}
