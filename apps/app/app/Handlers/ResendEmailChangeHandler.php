<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Email;
use App\Commands\ResendEmailChangeCommand;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;

/**
 * Sends the link to the waiting new email again, when one waits.
 */
final readonly class ResendEmailChangeHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private EmailConfirmation $emailConfirmation,
    ) {}

    public function handle(ResendEmailChangeCommand $resendEmailChangeCommand): void
    {
        $riderId = $resendEmailChangeCommand->riderId;

        $this->currentRider->assertSame($riderId);

        $pending = $this->userRepository->pendingEmail($riderId);

        if ($pending instanceof Email) {
            $this->emailConfirmation->requestChange($riderId, $pending);
        }
    }
}
