<?php

declare(strict_types=1);

use App\Account\AdminRules;
use App\Account\Permission;
use App\Account\Role;

covers(AdminRules::class, Role::class);

it('lets nobody change their own account from the admin area', function (Role $role): void {
    expect(AdminRules::mayManage($role, $role, self: true))->toBeFalse();
})->with(Role::cases());

it("keeps Super Admins out of an Admin's reach", function (): void {
    expect(AdminRules::mayManage(Role::Admin, Role::SuperAdmin, self: false))->toBeFalse()
        ->and(AdminRules::mayManage(Role::Admin, Role::Admin, self: false))->toBeTrue()
        ->and(AdminRules::mayManage(Role::Admin, Role::User, self: false))->toBeTrue()
        ->and(AdminRules::mayManage(Role::SuperAdmin, Role::SuperAdmin, self: false))->toBeTrue();
});

it('always keeps one Super Admin', function (): void {
    expect(AdminRules::keepsASuperAdmin(Role::SuperAdmin, Role::Admin, superAdmins: 1))->toBeFalse()
        ->and(AdminRules::keepsASuperAdmin(Role::SuperAdmin, next: null, superAdmins: 1))->toBeFalse()
        ->and(AdminRules::keepsASuperAdmin(Role::SuperAdmin, Role::User, superAdmins: 2))->toBeTrue()
        ->and(AdminRules::keepsASuperAdmin(Role::SuperAdmin, Role::SuperAdmin, superAdmins: 1))->toBeTrue()
        ->and(AdminRules::keepsASuperAdmin(Role::Admin, next: null, superAdmins: 0))->toBeTrue();
});

it('reads the strongest role a rider holds', function (): void {
    expect(Role::strongest([]))->toBe(Role::User)
        ->and(Role::strongest(['user', 'admin']))->toBe(Role::Admin)
        ->and(Role::strongest(['admin', 'super-admin', 'user']))->toBe(Role::SuperAdmin)
        ->and(Role::strongest(['unknown']))->toBe(Role::User);
});

it('gives an Admin everything but roles, and a User nothing', function (): void {
    expect(Role::Admin->permissions())->toBe([Permission::ViewAnalytics, Permission::ViewUsers, Permission::ManageUsers])
        ->and(Role::User->permissions())->toBe([])
        ->and(Role::SuperAdmin->permissions())->toBe([]);
});
