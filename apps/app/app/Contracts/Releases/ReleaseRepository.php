<?php

declare(strict_types=1);

namespace App\Contracts\Releases;

use App\Account\RiderId;
use DateTimeImmutable;

interface ReleaseRepository
{
    /**
     * Adds the release, or brings its name and notes up to date when the tag is known.
     */
    public function save(string $tag, string $name, string $notes, DateTimeImmutable $publishedAt): void;

    /**
     * Forgets the releases published since the given time that are not in the list: one
     * deleted on GitHub, turned back into a draft, or left with nothing for a rider.
     *
     * @param  list<string>  $tags
     */
    public function forgetAllBut(array $tags, DateTimeImmutable $since): void;

    /**
     * False when there is no such release.
     */
    public function markRead(RiderId $riderId, int $releaseId): bool;

    /**
     * Marks every release the rider is told about as read.
     */
    public function markAllRead(RiderId $riderId): void;
}
