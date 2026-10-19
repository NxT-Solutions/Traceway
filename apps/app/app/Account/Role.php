<?php

declare(strict_types=1);

namespace App\Account;

/**
 * What a person may do in Traceway beyond their own account. Every rider is a User. An Admin looks after riders
 * and reads the numbers. A Super Admin may do everything, roles included, through the gate.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    /**
     * The permissions stored on the role. A Super Admin passes every check before these are read, so it holds none.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::User => [],
            self::Admin => [Permission::ViewAnalytics, Permission::ViewUsers, Permission::ManageUsers],
        };
    }

    /**
     * The strongest of the roles a person holds. Someone with none is a User.
     *
     * @param  iterable<string>  $names
     */
    public static function strongest(iterable $names): self
    {
        $roles = [];

        foreach ($names as $name) {
            $role = self::tryFrom($name);

            if ($role instanceof self) {
                $roles[] = $role;
            }
        }

        foreach (self::cases() as $case) {
            if (in_array($case, $roles, strict: true)) {
                return $case;
            }
        }

        return self::User;
    }
}
