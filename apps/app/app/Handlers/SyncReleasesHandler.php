<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Releases\ReleaseRepository;
use App\Domain\Releases\ReleaseFeed;

final readonly class SyncReleasesHandler
{
    public function __construct(
        private ReleaseFeed $releaseFeed,
        private ReleaseRepository $releaseRepository,
    ) {}

    /**
     * @return int How many releases were saved.
     */
    public function handle(): int
    {
        $releases = $this->releaseFeed->published();
        foreach ($releases as $release) {
            $this->releaseRepository->save($release->tag, $release->name, $release->notes, $release->publishedAt);
        }

        return count($releases);
    }
}
