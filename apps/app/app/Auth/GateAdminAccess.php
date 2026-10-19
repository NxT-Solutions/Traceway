<?php

declare(strict_types=1);

namespace App\Auth;

use App\Account\AdminRules;
use App\Account\MissingRider;
use App\Account\Permission;
use App\Account\RiderId;
use App\Account\Role;
use App\Contracts\Auth\AdminAccess;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final readonly class GateAdminAccess implements AdminAccess
{
    public function assertAllowed(RiderId $riderId, Permission $permission): void
    {
        Gate::forUser($this->user($riderId))->authorize($permission->value);
    }

    public function assertMayManage(RiderId $actorId, RiderId $riderId, Permission $permission): void
    {
        $user = $this->user($actorId);

        Gate::forUser($user)->authorize($permission->value);

        throw_unless(AdminRules::mayManage(
            Role::strongest($user->getRoleNames()),
            Role::strongest($this->user($riderId)->getRoleNames()),
            $actorId->value === $riderId->value,
        ), AuthorizationException::class, 'You cannot change this account from the admin area.');
    }

    public function assertMayImpersonate(RiderId $actorId, RiderId $riderId): void
    {
        $user = $this->user($actorId);

        Gate::forUser($user)->authorize(Permission::Impersonate->value);

        throw_unless(AdminRules::mayImpersonate(
            Role::strongest($user->getRoleNames()),
            Role::strongest($this->user($riderId)->getRoleNames()),
            $actorId->value === $riderId->value,
        ), AuthorizationException::class, 'You cannot act as this rider.');
    }

    private function user(RiderId $riderId): User
    {
        $user = User::query()->find($riderId->value);

        if (! $user instanceof User) {
            throw MissingRider::id($riderId->value);
        }

        return $user;
    }
}
