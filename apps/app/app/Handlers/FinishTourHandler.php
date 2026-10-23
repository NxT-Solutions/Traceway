<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\FinishTourCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;

/**
 * The planner shows a rider around once. Finishing the tour or closing it early both count, so it does not come
 * back on the next visit or on another device.
 */
final readonly class FinishTourHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
    ) {}

    public function handle(FinishTourCommand $finishTourCommand): void
    {
        $this->currentRider->assertSame($finishTourCommand->riderId);

        $this->userRepository->markToured($finishTourCommand->riderId);
    }
}
