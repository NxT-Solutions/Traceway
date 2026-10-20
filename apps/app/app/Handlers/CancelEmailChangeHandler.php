<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\CancelEmailChangeCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;

/**
 * Forgets the new email the rider asked for. The link sent to it stops counting.
 */
final readonly class CancelEmailChangeHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
    ) {}

    public function handle(CancelEmailChangeCommand $cancelEmailChangeCommand): void
    {
        $this->currentRider->assertSame($cancelEmailChangeCommand->riderId);

        $this->userRepository->cancelEmailChange($cancelEmailChangeCommand->riderId);
    }
}
