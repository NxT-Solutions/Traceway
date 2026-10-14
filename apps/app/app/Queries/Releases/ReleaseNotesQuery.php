<?php

declare(strict_types=1);

namespace App\Queries\Releases;

use App\Domain\Releases\ReleaseWindow;
use App\Domain\Releases\RiderNotes;
use App\Queries\RowReader;
use Closure;
use DateTimeInterface;
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
    /**
     * For the bell in the header: how many are unread, and the newest few of them.
     *
     * @return array{unread: int, items: list<array{id: int, tag: string, name: string, published_at: string|null, headline: string|null}>}
     */
    public function summaryFor(int $riderId, ?DateTimeInterface $joinedAt): array
    {
        $builder = DB::table('releases')
            ->where('published_at', '>=', ReleaseWindow::opensAt($joinedAt ?? now()))
            ->whereNotExists($this->readBy($riderId));

        $rows = (clone $builder)
            ->latest('published_at')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'tag', 'name', 'summary', 'notes', 'published_at']);

        $items = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $items[] = [
                'id' => $reader->int('id'),
                'tag' => $reader->string('tag'),
                'name' => $reader->string('name'),
                'published_at' => $reader->iso('published_at'),
                'headline' => $reader->optionalString('summary') ?? RiderNotes::headline($reader->string('notes')),
            ];
        }

        return [
            'unread' => $builder->count(),
            'items' => $items,
        ];
    }

    /**
     * For the What's new page: every release the rider is told about, with its notes
     * ready to show. Images are left out, so opening the page loads nothing from the
     * hosts a pull request title might point at.
     *
     * @return list<array{id: int, tag: string, name: string, summary: string|null, published_at: string|null, read: bool, html: string}>
     */
    public function pageFor(int $riderId, ?DateTimeInterface $joinedAt): array
    {
        $rows = DB::table('releases')
            ->leftJoin('release_reads', function (JoinClause $joinClause) use ($riderId): void {
                $joinClause->on('release_reads.release_id', '=', 'releases.id')
                    ->where('release_reads.user_id', '=', $riderId);
            })
            ->where('releases.published_at', '>=', ReleaseWindow::opensAt($joinedAt ?? now()))
            ->latest('releases.published_at')
            ->latest('releases.id')
            ->get(['releases.id', 'releases.tag', 'releases.name', 'releases.summary', 'releases.notes', 'releases.published_at', 'release_reads.read_at']);

        $releases = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $releases[] = [
                'id' => $reader->int('id'),
                'tag' => $reader->string('tag'),
                'name' => $reader->string('name'),
                'summary' => $reader->optionalString('summary'),
                'published_at' => $reader->iso('published_at'),
                'read' => $reader->optionalString('read_at') !== null,
                'html' => $this->html($reader->string('notes')),
            ];
        }

        return $releases;
    }

    /**
     * Markdown from GitHub as HTML: raw HTML is escaped, unsafe links are dropped, an
     * image becomes its description, and headings start at h3.
     */
    private function html(string $notes): string
    {
        $html = Str::markdown($notes, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) preg_replace('/<img\b[^>]*\balt="([^"]*)"[^>]*>/i', '$1', $html);

        $html = (string) preg_replace('/<img\b[^>]*>/i', '', $html);

        // The release name is the page's h2, so the notes' own headings sit one level below it.
        return (string) preg_replace_callback(
            '/<(\/?)h([1-5])\b/i',
            fn (array $match): string => '<'.$match[1].'h'.max(3, (int) $match[2] + 1),
            $html,
        );
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
}
