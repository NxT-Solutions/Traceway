<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Releases\ReleaseRepository;
use App\Domain\Releases\ReleaseWindow;
use App\Models\Release;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class EloquentReleaseRepository implements ReleaseRepository
{
    public function save(string $tag, string $name, string $notes, DateTimeImmutable $publishedAt): void
    {
        Release::query()->updateOrCreate(['tag' => $tag], [
            'name' => $name,
            'notes' => $notes,
            'published_at' => $publishedAt,
        ]);
    }

    public function forgetAllBut(array $tags, DateTimeImmutable $since): void
    {
        DB::table('releases')
            ->where('published_at', '>=', $since)
            ->whereNotIn('tag', $tags)
            ->delete();
    }

    public function markRead(RiderId $riderId, int $releaseId): bool
    {
        if (! DB::table('releases')->where('id', $releaseId)->exists()) {
            return false;
        }

        DB::table('release_reads')->insertOrIgnore([
            'user_id' => $riderId->value,
            'release_id' => $releaseId,
            'read_at' => now(),
        ]);

        return true;
    }

    public function markAllRead(RiderId $riderId): void
    {
        $joined = DB::table('users')->where('id', $riderId->value)->value('created_at');

        $unread = DB::table('releases')
            ->where('published_at', '>=', ReleaseWindow::opensAt(is_string($joined) ? CarbonImmutable::parse($joined) : now()))
            ->whereNotExists(fn (Builder $builder): Builder => $builder->selectRaw('1')
                ->from('release_reads')
                ->whereColumn('release_reads.release_id', 'releases.id')
                ->where('release_reads.user_id', $riderId->value))
            ->pluck('id');

        $now = now();

        DB::table('release_reads')->insertOrIgnore($unread->map(fn (mixed $releaseId): array => [
            'user_id' => $riderId->value,
            'release_id' => $releaseId,
            'read_at' => $now,
        ])->all());
    }
}
