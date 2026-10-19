<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AdminRules;
use App\Account\Permission;
use App\Account\Role;
use App\Commands\AdminAssignRoleCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AdminAssignRoleHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
    ) {}

    public function handle(AdminAssignRoleCommand $adminAssignRoleCommand): void
    {
        $this->adminAccess->assertMayManage($adminAssignRoleCommand->actorId, $adminAssignRoleCommand->riderId, Permission::AssignRoles);

        $role = $this->userRepository->role($adminAssignRoleCommand->riderId);

        throw_unless(
            AdminRules::keepsASuperAdmin($role, $adminAssignRoleCommand->role, $this->userRepository->countWithRole(Role::SuperAdmin)),
            AuthorizationException::class,
            'Traceway keeps at least one Super Admin.',
        );

        $this->userRepository->assignRole($adminAssignRoleCommand->riderId, $adminAssignRoleCommand->role);
    }
}
