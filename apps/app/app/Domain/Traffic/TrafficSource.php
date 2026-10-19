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
     * Whether Traceway has the keys to ask for page loads and visits, and not only for requests.
     */
    public function readsVisits(): bool;

    /**
     * Requests cover the whole stretch. Page loads, visits, pages, referrers, and countries cover it from
     * `VisitWindow::start($until)` at the earliest. The days before that come from the snapshots.
     *
     * @throws TrafficUnavailable when the totals could not be read
     */
    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport;

    /**
     * Page loads, visits, pages, referrers, and countries only, as `report()` reads them, with no request days. The
     * daily snapshots need nothing else, so they do not wait on the requests.
     *
     * @throws TrafficUnavailable when the totals could not be read
     */
    public function visits(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport;
}
