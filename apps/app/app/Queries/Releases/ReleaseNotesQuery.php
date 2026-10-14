<?php

declare(strict_types=1);

namespace App\Queries\Releases;

use App\Domain\Releases\RiderNotes;
use App\Queries\RowReader;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What changed in Traceway, and what a rider has not read yet. A new rider sees the
 * releases from the month before they joined, not the whole history.
 */
final class ReleaseNotesQuery
{
    private const int DAYS_BEFORE_JOINING = 30;

    /**
     * For the bell in the header: how many are unread, and the newest few of them.
     *
     * @return array{unread: int, items: list<array{id: int, tag: string, name: string, published_at: string|null, headline: string|null}>}
     */
    public function summaryFor(int $riderId): array
    {
        $builder = $this->visibleTo($riderId)->whereNotExists($this->readBy($riderId));

        $rows = (clone $builder)
            ->latest('published_at')
            ->limit(5)
            ->get(['id', 'tag', 'name', 'notes', 'published_at']);

        $items = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $items[] = [
                'id' => $reader->int('id'),
                'tag' => $reader->string('tag'),
                'name' => $reader->string('name'),
                'published_at' => $reader->iso('published_at'),
                'headline' => RiderNotes::headline($reader->string('notes')),
            ];
        }

        return [
            'unread' => $builder->count(),
            'items' => $items,
        ];
    }

    /**
     * For the What's new page: the latest releases, with their notes ready to show.
     *
     * @return list<array{id: int, tag: string, name: string, published_at: string|null, read: bool, html: string}>
     */
    public function pageFor(int $riderId): array
    {
        $rows = DB::table('releases')
            ->leftJoin('release_reads', function (JoinClause $joinClause) use ($riderId): void {
                $joinClause->on('release_reads.release_id', '=', 'releases.id')
                    ->where('release_reads.user_id', '=', $riderId);
            })
            ->where('releases.published_at', '>=', $this->since($riderId))
            ->latest('releases.published_at')
            ->limit(20)
            ->get(['releases.id', 'releases.tag', 'releases.name', 'releases.notes', 'releases.published_at', 'release_reads.read_at']);

        $releases = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $releases[] = [
                'id' => $reader->int('id'),
                'tag' => $reader->string('tag'),
                'name' => $reader->string('name'),
                'published_at' => $reader->iso('published_at'),
                'read' => $reader->optionalString('read_at') !== null,
                'html' => Str::markdown($reader->string('notes'), [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
            ];
        }

        return $releases;
    }

    private function visibleTo(int $riderId): Builder
    {
        return DB::table('releases')->where('published_at', '>=', $this->since($riderId));
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function readBy(int $riderId): Closure
    {
        return fn (Builder $builder): Builder => $builder->selectRaw('1')
            ->from('release_reads')
            ->whereColumn('release_reads.release_id', 'releases.id')
            ->where('release_reads.user_id', $riderId);
    }

    private function since(int $riderId): CarbonImmutable
    {
        $joined = DB::table('users')->where('id', $riderId)->value('created_at');

        $joinedAt = is_string($joined) ? CarbonImmutable::parse($joined) : CarbonImmutable::now();

        return $joinedAt->subDays(self::DAYS_BEFORE_JOINING);
    }
}
