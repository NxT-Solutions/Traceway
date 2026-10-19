<?php

declare(strict_types=1);

use App\Account\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function riderWithRole(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('sends a guest to the sign-in page', function (string $path): void {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/admin', '/admin/traffic', '/admin/users']);

it('keeps a rider without a role out of the admin area', function (string $path): void {
    $this->actingAs(riderWithRole(Role::User))->get($path)->assertForbidden();
})->with(['/admin', '/admin/traffic', '/admin/users', '/admin/users/create']);

it('opens every admin page to an Admin and a Super Admin', function (Role $role, string $path, string $component): void {
    $this->actingAs(riderWithRole($role))
        ->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component($component));
})->with([Role::Admin, Role::SuperAdmin])->with([
    ['/admin', 'admin/overview'],
    ['/admin/traffic', 'admin/traffic'],
    ['/admin/users', 'admin/users/index'],
    ['/admin/users/create', 'admin/users/create'],
]);

it('lets only a Super Admin give roles', function (): void {
    $user = riderWithRole(Role::User);

    $this->actingAs(riderWithRole(Role::Admin))
        ->put(route('admin.users.role', $user->id), ['role' => Role::Admin->value])
        ->assertForbidden();

    expect($user->fresh()?->hasRole(Role::Admin->value))->toBeFalse();
});

it('tells the pages what the person may do', function (Role $role, bool $admin, bool $assignRoles): void {
    $this->actingAs(riderWithRole($role))
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('auth.can.admin', $admin)
            ->where('auth.can.assignRoles', $assignRoles));
})->with([
    [Role::User, false, false],
    [Role::Admin, true, false],
    [Role::SuperAdmin, true, true],
]);
