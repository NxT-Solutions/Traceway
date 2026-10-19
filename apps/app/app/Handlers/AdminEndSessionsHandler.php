<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\Permission;
use App\Commands\AdminEndSessionsCommand;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;

final readonly class AdminEndSessionsHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
    ) {}

    public function handle(AdminEndSessionsCommand $adminEndSessionsCommand): void
    {
        $this->adminAccess->assertMayManage($adminEndSessionsCommand->actorId, $adminEndSessionsCommand->riderId, Permission::ManageUsers);

        $this->userRepository->endSessions($adminEndSessionsCommand->riderId);
    }
}
