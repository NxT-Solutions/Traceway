<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Status\StatusRepository;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseStatusRepository implements StatusRepository
{
    /**
     * @param  mixed[][]  $checks
     */
    public function recordRun(array $checks, DateTimeImmutable $checkedAt): void
    {
        $at = CarbonImmutable::instance($checkedAt);

        DB::table('status_checks')->insert(array_map(fn (array $check): array => [
            'component' => $check['component'],
            'state' => $check['state'],
            'latency_ms' => max(0, $check['milliseconds']),
            'checked_at' => $at,
        ], $checks));
    }

    public function forgetChecksBefore(DateTimeImmutable $before): int
    {
        return DB::table('status_checks')->where('checked_at', '<', CarbonImmutable::instance($before))->delete();
    }

    public function postNotice(string $title, ?string $body, array $components, DateTimeImmutable $startedAt): int
    {
        $now = CarbonImmutable::instance($startedAt);

        return DB::table('status_notices')->insertGetId([
            'title' => $title,
            'body' => $body,
            'components' => json_encode($components, JSON_THROW_ON_ERROR),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function resolveNotice(?int $noticeId, DateTimeImmutable $resolvedAt): ?int
    {
        $id = DB::table('status_notices')
            ->whereNull('resolved_at')
            ->when($noticeId !== null, fn ($builder) => $builder->where('id', $noticeId))
            ->latest('started_at')
            ->latest('id')
            ->value('id');

        if (! is_numeric($id)) {
            return null;
        }

        $at = CarbonImmutable::instance($resolvedAt);
        DB::table('status_notices')->where('id', $id)->update(['resolved_at' => $at, 'updated_at' => $at]);

        return (int) $id;
    }
}
