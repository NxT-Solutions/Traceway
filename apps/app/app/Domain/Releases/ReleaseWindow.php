<?php

declare(strict_types=1);

namespace App\Domain\Releases;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * The releases a rider is told about: those from the month before they joined, and
 * every one since. A new rider does not get the whole history as unread.
 */
final class ReleaseWindow
{
    private const int DAYS_BEFORE_JOINING = 30;

    public static function opensAt(DateTimeInterface $joinedAt): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($joinedAt)->modify('-'.self::DAYS_BEFORE_JOINING.' days');
    }
}
