<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AccountCloser;
use App\Account\AdminRules;
use App\Account\Permission;
use App\Account\Role;
use App\Commands\AdminDeleteUserCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AdminDeleteUserHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
        private AccountCloser $accountCloser,
    ) {}

    public function handle(AdminDeleteUserCommand $adminDeleteUserCommand): void
    {
        $this->adminAccess->assertMayManage($adminDeleteUserCommand->actorId, $adminDeleteUserCommand->riderId, Permission::ManageUsers);

        throw_unless(
            AdminRules::keepsASuperAdmin($this->userRepository->role($adminDeleteUserCommand->riderId), next: null, superAdmins: $this->userRepository->countWithRole(Role::SuperAdmin)),
            AuthorizationException::class,
            'Traceway keeps at least one Super Admin.',
        );

        $this->accountCloser->close($adminDeleteUserCommand->riderId);
    }
}
