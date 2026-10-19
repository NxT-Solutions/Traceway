<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use App\Domain\Traffic\BackupHealth;
use App\Domain\Traffic\BotTraffic;
use App\Domain\Traffic\RequestBreakdown;
use App\Domain\Traffic\SecurityReport;
use App\Domain\Traffic\SpeedReport;
use App\Domain\Traffic\TrafficInsights;
use App\Integrations\Cloudflare\Insights\BackupsReader;
use App\Integrations\Cloudflare\Insights\BotsReader;
use App\Integrations\Cloudflare\Insights\RequestsReader;
use App\Integrations\Cloudflare\Insights\SecurityReader;
use App\Integrations\Cloudflare\Insights\SpeedReader;
use DateTimeImmutable;

/**
 * The free plan's analytics beyond the daily totals, with the same read-only token. A part whose id is not set
 * answers empty: the zone for requests, security, and bots; the account and site tag for speed; the account and
 * bucket for backups.
 */
final readonly class CloudflareInsights implements TrafficInsights
{
    public function __construct(
        private CloudflareGraphql $cloudflareGraphql,
        private ?string $zoneId,
        private ?string $accountId,
        private ?string $siteTag,
        private ?string $backupBucket,
        private ?string $turnstileSiteKey,
    ) {}

    public function connected(): bool
    {
        return true;
    }

    public function requests(DateTimeImmutable $now): RequestBreakdown
    {
        return $this->zoneId === null ? new RequestBreakdown : new RequestsReader($this->cloudflareGraphql, $this->zoneId)->read($now);
    }

    public function security(DateTimeImmutable $now): SecurityReport
    {
        return $this->zoneId === null
            ? new SecurityReport
            : new SecurityReader($this->cloudflareGraphql, $this->zoneId, $this->accountId, $this->turnstileSiteKey)->read($now);
    }

    public function bots(DateTimeImmutable $now): BotTraffic
    {
        return $this->zoneId === null ? new BotTraffic : new BotsReader($this->cloudflareGraphql, $this->zoneId)->read($now);
    }

    public function speed(DateTimeImmutable $now): SpeedReport
    {
        return $this->accountId === null || $this->siteTag === null
            ? new SpeedReport
            : new SpeedReader($this->cloudflareGraphql, $this->accountId, $this->siteTag)->read($now);
    }

    public function backups(DateTimeImmutable $now): BackupHealth
    {
        return $this->accountId === null || $this->backupBucket === null
            ? new BackupHealth
            : new BackupsReader($this->cloudflareGraphql, $this->accountId, $this->backupBucket)->read($now);
    }
}
