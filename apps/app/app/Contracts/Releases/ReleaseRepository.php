<?php

declare(strict_types=1);

namespace App\Contracts\Releases;

use App\Account\RiderId;
use DateTimeImmutable;

interface ReleaseRepository
{
    /**
     * Adds the release, or brings its title, summary, and notes up to date when the tag is
     * known. A release with no date keeps the one it first got, or gets now.
     */
    public function save(string $tag, string $title, ?string $summary, string $notes, ?DateTimeImmutable $publishedAt): void;

    /**
     * Forgets every release not in the list, such as one whose notes were removed.
     *
     * @param  list<string>  $tags
     */
    public function forgetAllBut(array $tags): void;

    /**
     * False when there is no such release.
     */
    public function markRead(RiderId $riderId, int $releaseId): bool;

    /**
     * Marks every release the rider is told about as read.
     */
    public function markAllRead(RiderId $riderId): void;
}
