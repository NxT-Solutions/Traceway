<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\Releases\ReleaseRepository;
use App\Domain\Releases\Release;
use App\Domain\Releases\ReleaseFeed;
use DateTimeImmutable;

final readonly class SyncReleasesHandler
{
    public function __construct(
        private ReleaseFeed $releaseFeed,
        private ReleaseRepository $releaseRepository,
    ) {}

    /**
     * Saves what GitHub lists, and forgets a release it no longer lists among them.
     *
     * @return int How many releases were saved.
     */
    public function handle(): int
    {
        $releases = $this->releaseFeed->published();
        foreach ($releases as $release) {
            $this->releaseRepository->save($release->tag, $release->name, $release->notes, $release->publishedAt);
        }

        if ($releases !== []) {
            $this->releaseRepository->forgetAllBut(
                array_map(fn (Release $release): string => $release->tag, $releases),
                min(array_map(fn (Release $release): DateTimeImmutable => $release->publishedAt, $releases)),
            );
        }

        return count($releases);
    }
}
