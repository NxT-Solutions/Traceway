<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AccountCloser;
use App\Account\AdminRules;
use App\Account\Role;
use App\Commands\DeleteAccountCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DeleteAccountHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private AccountCloser $accountCloser,
        private UserRepository $userRepository,
    ) {}

    public function handle(DeleteAccountCommand $deleteAccountCommand): void
    {
        $this->currentRider->assertSame($deleteAccountCommand->riderId);

        throw_unless(
            AdminRules::keepsASuperAdmin($this->userRepository->role($deleteAccountCommand->riderId), next: null, superAdmins: $this->userRepository->countWithRole(Role::SuperAdmin)),
            AuthorizationException::class,
            'You are the last Super Admin. Make someone else a Super Admin before you delete your account.',
        );

        $this->accountCloser->close($deleteAccountCommand->riderId);
    }
}
