<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\UpdateProfileCommand;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;

final readonly class UpdateProfileHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private EmailConfirmation $emailConfirmation,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(UpdateProfileCommand $updateProfileCommand): void
    {
        $this->currentRider->assertSame($updateProfileCommand->riderId);

        $rider = $this->userRepository->find($updateProfileCommand->riderId);
        $emailChanged = ! $rider->email->equals($updateProfileCommand->email);

        $this->userRepository->updateProfile(
            $updateProfileCommand->riderId,
            $updateProfileCommand->name,
            $updateProfileCommand->email,
            $emailChanged,
        );

        if (! $emailChanged) {
            return;
        }

        $this->emailConfirmation->request($updateProfileCommand->riderId);
        $this->accountMailer->emailChanged($updateProfileCommand->name, $rider->email, $updateProfileCommand->email);
    }
}
