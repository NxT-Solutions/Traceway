<?php

declare(strict_types=1);

namespace App\Account;

/**
 * What one person in the admin area may do to another, past the permission itself. These hold for a Super Admin
 * too, because the gate lets a Super Admin through every permission check.
 */
final readonly class AdminRules
{
    /**
     * Nobody changes their own account from the admin area; Settings is for that. An Admin looks after riders, not
     * other Admins, so one Admin cannot take over another's account. A Super Admin looks after everyone.
     */
    public static function mayManage(Role $actor, Role $target, bool $self): bool
    {
        if ($self) {
            return false;
        }

        return match ($actor) {
            Role::SuperAdmin => true,
            Role::Admin => $target === Role::User,
            Role::User => false,
        };
    }

    /**
     * Traceway always keeps one Super Admin, so the last one cannot lose the role or be deleted.
     */
    public static function keepsASuperAdmin(Role $target, ?Role $next, int $superAdmins): bool
    {
        if ($target !== Role::SuperAdmin || $next === Role::SuperAdmin) {
            return true;
        }

        return $superAdmins > 1;
    }
}
