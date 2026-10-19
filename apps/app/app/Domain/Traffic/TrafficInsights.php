<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;

/**
 * Everything else Cloudflare can say about traceway.online on the free plan: requests by kind, what it blocked,
 * which bots asked, how fast the pages are, and whether the backups are written. Each answer is a total, never a
 * person or an IP address.
 */
interface TrafficInsights
{
    /**
     * Whether Traceway has the keys to ask.
     */
    public function connected(): bool;

    /**
     * @throws TrafficUnavailable when Cloudflare could not say
     */
    public function requests(DateTimeImmutable $now): RequestBreakdown;

    /**
     * @throws TrafficUnavailable when Cloudflare could not say
     */
    public function security(DateTimeImmutable $now): SecurityReport;

    /**
     * @throws TrafficUnavailable when Cloudflare could not say
     */
    public function bots(DateTimeImmutable $now): BotTraffic;

    /**
     * @throws TrafficUnavailable when Cloudflare could not say
     */
    public function speed(DateTimeImmutable $now): SpeedReport;

    /**
     * @throws TrafficUnavailable when Cloudflare could not say
     */
    public function backups(DateTimeImmutable $now): BackupHealth;
}
