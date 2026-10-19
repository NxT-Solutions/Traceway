<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * The daily Web Analytics totals Traceway keeps, one row per finished UTC day, and how often a day not kept yet came
 * back empty.
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
     * The days that came back empty and are not kept yet, from the UTC day of `$from` to the UTC day of `$to`, both
     * included, oldest first.
     *
     * @return list<EmptyDay>
     */
    public function emptyDays(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Writes how often the day came back empty, or replaces the count already kept.
     */
    public function saveEmpty(EmptyDay $emptyDay): void;

    /**
     * Forgets how often the day came back empty, because it is kept or it had page loads after all.
     *
     * @param  string  $date  The UTC day, as Y-m-d.
     */
    public function forgetEmpty(string $date): void;

    /**
     * Forgets the days, and the counts of empty answers, before the UTC day of this moment.
     *
     * @return int How many kept days were forgotten.
     */
    public function forgetBefore(DateTimeImmutable $day): int;
}
