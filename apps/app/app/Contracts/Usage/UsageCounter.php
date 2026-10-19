<?php

declare(strict_types=1);

namespace App\Contracts\Usage;

use DateTimeImmutable;

/**
 * Daily totals of how Traceway is used. A total names a day and a metric, never a person.
 */
interface UsageCounter
{
    /**
     * Adds to the metric's total for the day of this moment.
     */
    public function add(string $metric, DateTimeImmutable $at, int $by = 1): void;

    /**
     * Writes a gauge for the day as it stands.
     */
    public function set(string $metric, DateTimeImmutable $day, int $value): void;

    /**
     * How many riders were seen since this moment.
     */
    public function ridersSeenSince(DateTimeImmutable $since): int;

    /**
     * Forgets the totals of the days before this one.
     */
    public function forgetBefore(DateTimeImmutable $day): int;
}
