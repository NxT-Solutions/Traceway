<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * The daily Web Analytics totals Traceway keeps, one row per finished UTC day.
 */
interface TrafficSnapshots
{
    /**
     * Writes the day, or replaces the one already kept.
     */
    public function save(TrafficDay $trafficDay): void;

    /**
     * The kept days from the UTC day of `$from` to the UTC day of `$to`, both included, oldest first.
     *
     * @return list<TrafficDay>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Forgets the days before the UTC day of this moment.
     */
    public function forgetBefore(DateTimeImmutable $day): int;
}
