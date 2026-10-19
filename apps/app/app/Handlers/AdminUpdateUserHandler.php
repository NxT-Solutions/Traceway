<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Permission;
use App\Commands\AdminUpdateUserCommand;
use App\Contracts\Account\EmailConfirmation;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;
use App\Contracts\Mail\AccountMailer;

final readonly class AdminUpdateUserHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
        private EmailConfirmation $emailConfirmation,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(AdminUpdateUserCommand $adminUpdateUserCommand): void
    {
        $this->adminAccess->assertMayManage($adminUpdateUserCommand->actorId, $adminUpdateUserCommand->riderId, Permission::ManageUsers);

        $rider = $this->userRepository->find($adminUpdateUserCommand->riderId);
        $emailChanged = ! $rider->email->equals($adminUpdateUserCommand->email);

        $this->userRepository->updateProfile(
            $adminUpdateUserCommand->riderId,
            $adminUpdateUserCommand->name,
            $adminUpdateUserCommand->email,
            $emailChanged && ! $adminUpdateUserCommand->confirmEmail,
        );

        if ($adminUpdateUserCommand->confirmEmail) {
            $this->userRepository->confirmEmail($adminUpdateUserCommand->riderId);
        }

        if (! $emailChanged) {
            return;
        }

        if (! $adminUpdateUserCommand->confirmEmail) {
            $this->emailConfirmation->request($adminUpdateUserCommand->riderId);
        }

        $this->accountMailer->emailChanged($adminUpdateUserCommand->name, $rider->email, $adminUpdateUserCommand->email);
    }
}
