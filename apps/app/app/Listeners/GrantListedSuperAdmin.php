<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Account\AdminAction;
use App\Account\RiderId;
use App\Account\Role;
use App\Contracts\Account\AdminLog;
use App\Contracts\Account\UserRepository;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Makes a listed address a Super Admin when its owner confirms it. Waiting for the confirmation means a sign-up
 * with someone else's address never gets the role.
 */
final readonly class GrantListedSuperAdmin
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminLog $adminLog,
    ) {}

    public function handle(Verified $verified): void
    {
        $user = $verified->user;

        if (! $user instanceof User) {
            return;
        }

        /** @var list<string> $superAdmins */
        $superAdmins = config('admin.super_admins', []);

        if (! in_array(mb_strtolower($user->email), $superAdmins, strict: true)) {
            return;
        }

        $riderId = new RiderId($user->id);
        $role = $this->userRepository->role($riderId);

        if ($role === Role::SuperAdmin) {
            return;
        }

        $this->userRepository->assignRole($riderId, Role::SuperAdmin);
        $this->adminLog->record(actorId: null, adminAction: AdminAction::RoleChanged, riderId: $riderId, details: ['from' => $role->value, 'to' => Role::SuperAdmin->value]);
    }
}
