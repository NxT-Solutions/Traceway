<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * The insights when no Analytics token is set, as in development and tests.
 */
final readonly class UnconnectedTrafficInsights implements TrafficInsights
{
    public function connected(): bool
    {
        return false;
    }

    public function requests(DateTimeImmutable $now): RequestBreakdown
    {
        return new RequestBreakdown;
    }

    public function security(DateTimeImmutable $now): SecurityReport
    {
        return new SecurityReport;
    }

    public function bots(DateTimeImmutable $now): BotTraffic
    {
        return new BotTraffic;
    }

    public function speed(DateTimeImmutable $now): SpeedReport
    {
        return new SpeedReport;
    }

    public function backups(DateTimeImmutable $now): BackupHealth
    {
        return new BackupHealth;
    }
}
