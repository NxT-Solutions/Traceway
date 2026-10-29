<?php

declare(strict_types=1);

namespace App\Domain\Status;

use DateTimeImmutable;

/**
 * How far back the status page looks, and how much one bar holds. It opens on
 * the last 24 hours, an hour to a bar, so an outage this morning reads as the
 * hours it lasted rather than a whole red day.
 */
enum StatusRange: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /** How many bars the range shows, the newest last. */
    public function bars(): int
    {
        return match ($this) {
            self::Day => 24,
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 90,
            self::Year => 52,
        };
    }

    /** How many hours one bar holds: an hour, a day, or a week. */
    public function hours(): int
    {
        return match ($this) {
            self::Day => 1,
            self::Year => 168,
            default => 24,
        };
    }

    /**
     * Where the first bar starts. The last bar holds now: the hour so far, the
     * day so far, or the week that ends today.
     */
    public function firstBar(DateTimeImmutable $now): DateTimeImmutable
    {
        $last = match ($this) {
            self::Day => $now->setTime((int) $now->format('G'), 0),
            self::Year => $now->setTime(0, 0)->modify('-6 days'),
            default => $now->setTime(0, 0),
        };

        return $last->modify(sprintf('-%d hours', ($this->bars() - 1) * $this->hours()));
    }
}
