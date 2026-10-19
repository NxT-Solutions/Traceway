<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Permission;
use App\Commands\AdminResetTwoFactorCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;

final readonly class AdminResetTwoFactorHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
    ) {}

    public function handle(AdminResetTwoFactorCommand $adminResetTwoFactorCommand): void
    {
        $this->adminAccess->assertMayManage($adminResetTwoFactorCommand->actorId, $adminResetTwoFactorCommand->riderId, Permission::ManageUsers);

        $this->userRepository->resetTwoFactor($adminResetTwoFactorCommand->riderId);
    }
}
