<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\UpdateProfileCommand;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;

/**
 * The name changes at once. A new email only waits: the account keeps signing in with the email it has until the
 * rider opens the link sent to the new one, so nobody can move an account onto an address they do not read.
 */
final readonly class UpdateProfileHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private EmailConfirmation $emailConfirmation,
    ) {}

    public function handle(UpdateProfileCommand $updateProfileCommand): void
    {
        $this->currentRider->assertSame($updateProfileCommand->riderId);

        $rider = $this->userRepository->find($updateProfileCommand->riderId);

        $this->userRepository->updateProfile($updateProfileCommand->riderId, $updateProfileCommand->name, $rider->email, clearEmailVerification: false);

        if ($rider->email->equals($updateProfileCommand->email)) {
            return;
        }

        $this->userRepository->requestEmailChange($updateProfileCommand->riderId, $updateProfileCommand->email);
        $this->emailConfirmation->requestChange($updateProfileCommand->riderId, $updateProfileCommand->email);
    }
}
