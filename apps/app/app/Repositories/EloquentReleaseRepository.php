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
    public function save(string $tag, string $title, ?string $summary, string $notes, ?DateTimeImmutable $publishedAt): void
    {
        $release = Release::query()->firstOrNew(['tag' => $tag]);
        $release->fill([
            'name' => $title,
            'summary' => $summary,
            'notes' => $notes,
        ]);

        if ($publishedAt instanceof DateTimeImmutable) {
            $release->fill(['published_at' => CarbonImmutable::instance($publishedAt)]);
        } elseif (! $release->exists) {
            $release->fill(['published_at' => CarbonImmutable::now()]);
        }

        $release->save();
    }

    public function forgetAllBut(array $tags): void
    {
        DB::table('releases')->whereNotIn('tag', $tags)->delete();
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
