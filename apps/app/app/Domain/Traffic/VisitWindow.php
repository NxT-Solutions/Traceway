<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Web Analytics answers in full only for a stretch that starts less than a week ago. One that starts earlier comes
 * back from a coarser store, with recent days missing and no visits, and no error. So visits are asked live for the
 * last seven UTC days, and the days before them are read from the daily snapshots.
 */
final class VisitWindow
{
    public const int DAYS = 7;

    /**
     * Midnight UTC of the first day that is asked live: six days before the day of this moment.
     */
    public static function start(DateTimeImmutable $until): DateTimeImmutable
    {
        return $until->setTimezone(new DateTimeZone('UTC'))
            ->setTime(0, 0)
            ->sub(new DateInterval('P'.(self::DAYS - 1).'D'));
    }
}
