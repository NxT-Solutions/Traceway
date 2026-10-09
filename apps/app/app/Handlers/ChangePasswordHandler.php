<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ChangePasswordCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(ChangePasswordCommand $changePasswordCommand): void
    {
        $this->currentRider->assertSameOrGuest($changePasswordCommand->riderId);

        $rider = $this->userRepository->changePassword($changePasswordCommand->riderId, $changePasswordCommand->password);
        $this->accountMailer->passwordChanged($rider->name, $rider->email);
    }
}
