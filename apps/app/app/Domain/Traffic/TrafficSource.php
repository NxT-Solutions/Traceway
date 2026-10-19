<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * Where the totals of visits and requests to traceway.online come from. They are counts only, never a person.
 */
interface TrafficSource
{
    /**
     * Whether Traceway has the keys to ask. Without them the Traffic page says so.
     */
    public function connected(): bool;

    /**
     * Requests cover the whole stretch. Page loads, visits, pages, referrers, and countries cover it from
     * `VisitWindow::start($until)` at the earliest. The days before that come from the snapshots.
     *
     * @throws TrafficUnavailable when the totals could not be read
     */
    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport;
}
