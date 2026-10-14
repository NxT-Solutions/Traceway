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
     * False when there is no such release.
     */
    public function markRead(RiderId $riderId, int $releaseId): bool;

    public function markAllRead(RiderId $riderId): void;
}
