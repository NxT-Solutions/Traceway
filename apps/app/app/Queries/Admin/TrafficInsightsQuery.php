<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Domain\Traffic\BackupHealth;
use App\Domain\Traffic\BotTraffic;
use App\Domain\Traffic\RequestBreakdown;
use App\Domain\Traffic\SecurityReport;
use App\Domain\Traffic\SpeedReport;
use App\Domain\Traffic\TrafficInsights;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficUnavailable;
use App\Domain\Traffic\WebVital;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * The admin area's Requests, Security, AI and bots, Speed, and Backups pages, from Cloudflare. Each part asks on
 * its own, keeps an answer for an hour and a failure for five minutes, and a failure shows as a note on that page
 * only.
 *
 * @phpstan-import-type Ranked from TrafficReport
 * @phpstan-import-type Read from BotTraffic
 * @phpstan-import-type StatusCount from BotTraffic
 * @phpstan-import-type DayCount from SecurityReport
 * @phpstan-import-type Hour from RequestBreakdown
 * @phpstan-import-type Day from RequestBreakdown
 * @phpstan-import-type PageTimings from SpeedReport
 *
 * @phpstan-type Vital array{value: float, rating: string, label: string}|null
 * @phpstan-type PageVitals array{path: string, loads: int, lcp: Vital, inp: Vital, cls: Vital}
 * @phpstan-type Part array{connected: bool, error: string|null, generated_at: string}
 */
final readonly class TrafficInsightsQuery
{
    public function __construct(
        private TrafficInsights $trafficInsights,
        private CacheRepository $cacheRepository,
    ) {}

    /**
     * @return array{connected: bool, error: string|null, generated_at: string, days: int, year_days: int, hours: list<Hour>, year: list<Day>, statuses: list<StatusCount>, content_types: list<Ranked>, browsers: list<Ranked>, http_versions: list<Ranked>, ip_classes: list<Ranked>}
     */
    public function requests(): array
    {
        /** @var array{connected: bool, error: string|null, generated_at: string, days: int, year_days: int, hours: list<Hour>, year: list<Day>, statuses: list<StatusCount>, content_types: list<Ranked>, browsers: list<Ranked>, http_versions: list<Ranked>, ip_classes: list<Ranked>} */
        return $this->part('requests', fn (CarbonImmutable $now): RequestBreakdown => $this->trafficInsights->requests($now->toDateTimeImmutable()), fn (RequestBreakdown $requestBreakdown): array => [
            'days' => RequestBreakdown::DAYS,
            'year_days' => RequestBreakdown::YEAR_DAYS,
            'hours' => $requestBreakdown->hours,
            'year' => $requestBreakdown->days,
            'statuses' => $requestBreakdown->statuses,
            'content_types' => $requestBreakdown->contentTypes,
            'browsers' => $requestBreakdown->browsers,
            'http_versions' => $requestBreakdown->httpVersions,
            'ip_classes' => $requestBreakdown->ipClasses,
        ], new RequestBreakdown);
    }

    /**
     * @return array{connected: bool, error: string|null, generated_at: string, days: int, turnstile_days: int, event_limit: int, threat_days: list<DayCount>, threat_countries: list<Ranked>, block_days: list<DayCount>, blocks: int, events: int, sources: list<Ranked>, actions: list<Ranked>, countries: list<Ranked>, paths: list<Ranked>, user_agents: list<Ranked>, rules: list<Ranked>, people_pages: list<Ranked>, turnstile: list<Ranked>}
     */
    public function security(): array
    {
        /** @var array{connected: bool, error: string|null, generated_at: string, days: int, turnstile_days: int, event_limit: int, threat_days: list<DayCount>, threat_countries: list<Ranked>, block_days: list<DayCount>, blocks: int, events: int, sources: list<Ranked>, actions: list<Ranked>, countries: list<Ranked>, paths: list<Ranked>, user_agents: list<Ranked>, rules: list<Ranked>, people_pages: list<Ranked>, turnstile: list<Ranked>} */
        return $this->part('security', fn (CarbonImmutable $now): SecurityReport => $this->trafficInsights->security($now->toDateTimeImmutable()), fn (SecurityReport $securityReport): array => [
            'days' => SecurityReport::DAYS,
            'turnstile_days' => SecurityReport::TURNSTILE_DAYS,
            'event_limit' => SecurityReport::EVENT_LIMIT,
            'threat_days' => $securityReport->threatDays,
            'threat_countries' => $securityReport->threatCountries,
            'block_days' => $securityReport->blockDays,
            'blocks' => array_sum(array_column($securityReport->blockDays, 'count')),
            'events' => $securityReport->events,
            'sources' => $securityReport->sources,
            'actions' => $securityReport->actions,
            'countries' => $securityReport->countries,
            'paths' => $securityReport->paths,
            'user_agents' => $securityReport->userAgents,
            'rules' => $securityReport->rules,
            'people_pages' => $securityReport->peoplePages,
            'turnstile' => $securityReport->turnstile,
        ], new SecurityReport);
    }

    /**
     * @return array{connected: bool, error: string|null, generated_at: string, days: int, verified: list<Ranked>, verified_total: int, reads: list<Read>, reads_total: int, readers: list<Ranked>, impostors: list<Ranked>, impostors_total: int, impostors_blocked: int, probes: list<StatusCount>, probes_total: int, exposed: list<Ranked>}
     */
    public function bots(): array
    {
        /** @var array{connected: bool, error: string|null, generated_at: string, days: int, verified: list<Ranked>, verified_total: int, reads: list<Read>, reads_total: int, readers: list<Ranked>, impostors: list<Ranked>, impostors_total: int, impostors_blocked: int, probes: list<StatusCount>, probes_total: int, exposed: list<Ranked>} */
        return $this->part('bots', fn (CarbonImmutable $now): BotTraffic => $this->trafficInsights->bots($now->toDateTimeImmutable()), fn (BotTraffic $botTraffic): array => [
            'days' => BotTraffic::DAYS,
            'verified' => $botTraffic->verified,
            'verified_total' => array_sum(array_column($botTraffic->verified, 'count')),
            'reads' => $botTraffic->reads,
            'reads_total' => $botTraffic->readsTotal,
            'readers' => $botTraffic->readers,
            'impostors' => $botTraffic->impostors,
            'impostors_total' => $botTraffic->impostorsTotal,
            'impostors_blocked' => $botTraffic->impostorsBlocked,
            'probes' => $botTraffic->probes,
            'probes_total' => array_sum(array_column($botTraffic->probes, 'count')),
            'exposed' => $botTraffic->exposed,
        ], new BotTraffic);
    }

    /**
     * @return array{connected: bool, error: string|null, generated_at: string, days: int, vitals: list<PageVitals>, timings: list<PageTimings>}
     */
    public function speed(): array
    {
        /** @var array{connected: bool, error: string|null, generated_at: string, days: int, vitals: list<PageVitals>, timings: list<PageTimings>} */
        return $this->part('speed', fn (CarbonImmutable $now): SpeedReport => $this->trafficInsights->speed($now->toDateTimeImmutable()), fn (SpeedReport $speedReport): array => [
            'days' => SpeedReport::DAYS,
            'vitals' => array_map(fn (array $page): array => [
                'path' => $page['path'],
                'loads' => $page['loads'],
                'lcp' => $this->vital(WebVital::Lcp, $page['lcp_ms']),
                'inp' => $this->vital(WebVital::Inp, $page['inp_ms']),
                'cls' => $this->vital(WebVital::Cls, $page['cls']),
            ], $speedReport->vitals),
            'timings' => $speedReport->timings,
        ], new SpeedReport);
    }

    /**
     * @return array{connected: bool, error: string|null, generated_at: string, days: int, bucket: string|null, objects: int, bytes: int, write_days: list<DayCount>, writes: int, last_write: string|null}
     */
    public function backups(): array
    {
        /** @var array{connected: bool, error: string|null, generated_at: string, days: int, bucket: string|null, objects: int, bytes: int, write_days: list<DayCount>, writes: int, last_write: string|null} */
        return $this->part('backups', fn (CarbonImmutable $now): BackupHealth => $this->trafficInsights->backups($now->toDateTimeImmutable()), fn (BackupHealth $backupHealth): array => [
            'days' => BackupHealth::DAYS,
            'bucket' => $backupHealth->bucket,
            'objects' => $backupHealth->objects,
            'bytes' => $backupHealth->bytes,
            'write_days' => $backupHealth->writeDays,
            'writes' => array_sum(array_column($backupHealth->writeDays, 'count')),
            'last_write' => $backupHealth->lastWrite,
        ], new BackupHealth);
    }

    /**
     * Asks once an hour for one part, and keeps a failure for five minutes with the part empty.
     *
     * @template T of object
     *
     * @param  Closure(CarbonImmutable): T  $ask
     * @param  Closure(T): array<string, mixed>  $shape
     * @param  T  $empty
     * @return array<string, mixed>
     */
    private function part(string $name, Closure $ask, Closure $shape, object $empty): array
    {
        $now = CarbonImmutable::now();

        if (! $this->trafficInsights->connected()) {
            return ['connected' => false, 'error' => null, 'generated_at' => $now->toIso8601String(), ...$shape($empty)];
        }

        $key = sprintf('admin-traffic-%s:%s', $name, $now->format('Y-m-d-H'));
        $cached = $this->cacheRepository->get($key);

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        try {
            $page = ['connected' => true, 'error' => null, 'generated_at' => $now->toIso8601String(), ...$shape($ask($now))];
            $this->cacheRepository->put($key, $page, 3600);
        } catch (TrafficUnavailable $trafficUnavailable) {
            $page = ['connected' => true, 'error' => $trafficUnavailable->getMessage(), 'generated_at' => $now->toIso8601String(), ...$shape($empty)];
            $this->cacheRepository->put($key, $page, 300);
        }

        return $page;
    }

    /**
     * @return Vital
     */
    private function vital(WebVital $webVital, ?float $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $vitalRating = $webVital->rating($value);

        return ['value' => $value, 'rating' => $vitalRating->value, 'label' => $vitalRating->label()];
    }
}
