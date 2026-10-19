<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\AdminAction;
use App\Account\Permission;
use App\Account\Rider;
use App\Commands\AdminCreateUserCommand;
use App\Contracts\Account\AdminLog;
use App\Contracts\Account\PasswordLink;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\AdminAccess;

final readonly class AdminCreateUserHandler
{
    public function __construct(
        private AdminAccess $adminAccess,
        private UserRepository $userRepository,
        private AdminLog $adminLog,
        private PasswordLink $passwordLink,
    ) {}

    public function handle(AdminCreateUserCommand $adminCreateUserCommand): Rider
    {
        $this->adminAccess->assertAllowed($adminCreateUserCommand->actorId, Permission::ManageUsers);

        // Nobody knows this password. The rider chooses their own from the link.
        $rider = $this->userRepository->createConfirmed($adminCreateUserCommand->name, $adminCreateUserCommand->email, bin2hex(random_bytes(32)));
        $this->passwordLink->send($rider->id);
        $this->adminLog->record($adminCreateUserCommand->actorId, AdminAction::Created, $rider->id);

        return $rider;
    }
}
