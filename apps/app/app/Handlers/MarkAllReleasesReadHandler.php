<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\MarkAllReleasesReadCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Releases\ReleaseRepository;

final readonly class MarkAllReleasesReadHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ReleaseRepository $releaseRepository,
    ) {}

    public function handle(MarkAllReleasesReadCommand $markAllReleasesReadCommand): void
    {
        $this->currentRider->assertSame($markAllReleasesReadCommand->riderId);

        $this->releaseRepository->markAllRead($markAllReleasesReadCommand->riderId);
    }
}
