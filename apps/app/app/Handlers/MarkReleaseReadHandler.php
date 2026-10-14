<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\MarkReleaseReadCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Releases\ReleaseRepository;

final readonly class MarkReleaseReadHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ReleaseRepository $releaseRepository,
    ) {}

    public function handle(MarkReleaseReadCommand $markReleaseReadCommand): void
    {
        $this->currentRider->assertSame($markReleaseReadCommand->riderId);

        abort_unless($this->releaseRepository->markRead($markReleaseReadCommand->riderId, $markReleaseReadCommand->releaseId), 404);
    }
}
