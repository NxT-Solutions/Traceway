<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Traffic\EmptyDay;
use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSnapshots;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * @phpstan-import-type Ranked from TrafficReport
 */
final readonly class DatabaseTrafficSnapshots implements TrafficSnapshots
{
    public function save(TrafficDay $trafficDay): void
    {
        DB::table('traffic_days')->upsert(
            [[
                'day' => $trafficDay->date,
                'page_loads' => max(0, $trafficDay->pageLoads),
                'visits' => max(0, $trafficDay->visits),
                'top_pages' => json_encode($trafficDay->topPages, JSON_THROW_ON_ERROR),
                'top_referrers' => json_encode($trafficDay->topReferrers, JSON_THROW_ON_ERROR),
                'countries' => json_encode($trafficDay->countries, JSON_THROW_ON_ERROR),
                'recorded_at' => CarbonImmutable::now(),
            ]],
            ['day'],
            ['page_loads', 'visits', 'top_pages', 'top_referrers', 'countries', 'recorded_at'],
        );
    }

    /**
     * @return list<TrafficDay>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $days = [];

        foreach (DB::table('traffic_days')->whereBetween('day', [$this->day($from), $this->day($to)])->orderBy('day')->get() as $row) {
            $days[] = new TrafficDay(
                is_string($row->day) ? substr($row->day, 0, 10) : '',
                is_numeric($row->page_loads) ? (int) $row->page_loads : 0,
                is_numeric($row->visits) ? (int) $row->visits : 0,
                $this->ranked($row->top_pages),
                $this->ranked($row->top_referrers),
                $this->ranked($row->countries),
            );
        }

        return $days;
    }

    /**
     * @return list<EmptyDay>
     */
    public function emptyDays(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $days = [];

        foreach (DB::table('traffic_empty_days')->whereBetween('day', [$this->day($from), $this->day($to)])->orderBy('day')->get() as $row) {
            $days[] = new EmptyDay(
                is_string($row->day) ? substr($row->day, 0, 10) : '',
                is_numeric($row->answers) ? (int) $row->answers : 0,
                is_string($row->last_counted_at) ? CarbonImmutable::parse($row->last_counted_at, 'UTC')->toDateTimeImmutable() : CarbonImmutable::now()->toDateTimeImmutable(),
            );
        }

        return $days;
    }

    public function saveEmpty(EmptyDay $emptyDay): void
    {
        DB::table('traffic_empty_days')->upsert(
            [[
                'day' => $emptyDay->date,
                'answers' => max(0, $emptyDay->answers),
                'last_counted_at' => CarbonImmutable::instance($emptyDay->lastCountedAt)->utc(),
            ]],
            ['day'],
            ['answers', 'last_counted_at'],
        );
    }

    public function forgetEmpty(string $date): void
    {
        DB::table('traffic_empty_days')->where('day', $date)->delete();
    }

    public function forgetBefore(DateTimeImmutable $day): int
    {
        DB::table('traffic_empty_days')->where('day', '<', $this->day($day))->delete();

        return DB::table('traffic_days')->where('day', '<', $this->day($day))->delete();
    }

    private function day(DateTimeImmutable $at): string
    {
        return CarbonImmutable::instance($at)->utc()->toDateString();
    }

    /**
     * @return list<Ranked>
     */
    private function ranked(mixed $column): array
    {
        try {
            $decoded = is_string($column) ? json_decode($column, associative: true, depth: 4, flags: JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            return [];
        }

        $ranked = [];

        foreach (is_array($decoded) ? $decoded : [] as $row) {
            if (is_array($row) && is_string($row['label'] ?? null) && is_int($row['count'] ?? null)) {
                $ranked[] = ['label' => $row['label'], 'count' => $row['count']];
            }
        }

        return $ranked;
    }
}
