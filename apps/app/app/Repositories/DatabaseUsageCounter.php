<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Usage\UsageCounter;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseUsageCounter implements UsageCounter
{
    public function add(string $metric, DateTimeImmutable $at, int $by = 1): void
    {
        DB::table('usage_counters')->upsert(
            [['day' => $this->day($at), 'metric' => $metric, 'count' => $by]],
            ['day', 'metric'],
            ['count' => DB::raw('usage_counters.count + excluded.count')],
        );
    }

    public function set(string $metric, DateTimeImmutable $day, int $value): void
    {
        DB::table('usage_counters')->upsert(
            [['day' => $this->day($day), 'metric' => $metric, 'count' => max(0, $value)]],
            ['day', 'metric'],
            ['count'],
        );
    }

    public function ridersSeenSince(DateTimeImmutable $since): int
    {
        return DB::table('users')->where('last_seen_at', '>=', CarbonImmutable::instance($since))->count();
    }

    public function forgetBefore(DateTimeImmutable $day): int
    {
        return DB::table('usage_counters')->where('day', '<', $this->day($day))->delete();
    }

    private function day(DateTimeImmutable $at): string
    {
        return CarbonImmutable::instance($at)->utc()->toDateString();
    }
}
