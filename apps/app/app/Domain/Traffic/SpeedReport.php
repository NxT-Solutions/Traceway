<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * How fast the site's pages are for the people who said yes to counting, over the last 7 days. Web Analytics
 * answers in full only for a stretch that starts less than a week ago. Each figure is the 75th percentile, as
 * Google rates them, and null when nothing was measured.
 *
 * @phpstan-type PageVitals array{path: string, loads: int, lcp_ms: float|null, inp_ms: float|null, cls: float|null}
 * @phpstan-type PageTimings array{path: string, loads: int, load_ms: float|null, first_paint_ms: float|null}
 */
final readonly class SpeedReport
{
    public const int DAYS = 7;

    /**
     * @param  list<PageVitals>  $vitals
     * @param  list<PageTimings>  $timings
     */
    public function __construct(
        public array $vitals = [],
        public array $timings = [],
    ) {}
}
