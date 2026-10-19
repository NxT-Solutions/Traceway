<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * The traffic source when no Analytics token is set, as in development and tests.
 */
final readonly class UnconnectedTrafficSource implements TrafficSource
{
    public function connected(): bool
    {
        return false;
    }

    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        return new TrafficReport([], [], [], [], []);
    }
}
