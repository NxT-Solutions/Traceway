<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Releases\ReleaseRepository;
use App\Domain\Releases\Release;
use App\Domain\Releases\ReleaseFeed;

final readonly class SyncReleasesHandler
{
    public function __construct(
        private ReleaseFeed $releaseFeed,
        private ReleaseRepository $releaseRepository,
    ) {}

    /**
     * Saves the rider notes of every released version, and forgets a version whose
     * notes are no longer kept.
     *
     * @return int How many releases were saved.
     */
    public function handle(): int
    {
        $releases = $this->releaseFeed->published();

        foreach ($releases as $release) {
            $this->releaseRepository->save($release->tag, $release->title, $release->summary, $release->notes, $release->publishedAt);
        }

        if ($releases !== []) {
            $this->releaseRepository->forgetAllBut(array_map(fn (Release $release): string => $release->tag, $releases));
        }

        return count($releases);
    }
}
