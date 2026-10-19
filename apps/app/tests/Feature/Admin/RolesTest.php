<?php

declare(strict_types=1);

use App\Account\LastSuperAdmin;
use App\Account\RiderId;
use App\Account\Role;
use App\Contracts\Account\UserRepository;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();
    config(['admin.super_admins' => ['boss@traceway.test']]);
});

it('makes every new rider a User', function (): void {
    $this->post(route('register.store'), [
        'name' => 'New rider',
        'email' => 'new@traceway.test',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ]);

    $user = User::query()->where('email', 'new@traceway.test')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe([Role::User->value]);
});

it('makes a listed address a Super Admin once it is confirmed', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'Boss@traceway.test']);
    $user->assignRole(Role::User->value);

    expect($user->hasRole(Role::SuperAdmin->value))->toBeFalse();

    $user->markEmailAsVerified();
    event(new Verified($user));

    expect($user->fresh()?->getRoleNames()->all())->toBe([Role::SuperAdmin->value]);
});

it('leaves an address that is not listed a User when it is confirmed', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'someone@traceway.test']);
    $user->assignRole(Role::User->value);

    $user->markEmailAsVerified();
    event(new Verified($user));

    expect($user->fresh()?->getRoleNames()->all())->toBe([Role::User->value]);
});

it('gives a role from the command line, but never takes the last Super Admin', function (): void {
    $boss = User::factory()->create(['email' => 'boss@traceway.test']);
    $boss->assignRole(Role::SuperAdmin->value);

    $rider = User::factory()->create(['email' => 'rider@traceway.test']);

    expect(Artisan::call('users:role', ['email' => 'rider@traceway.test', 'role' => 'admin']))->toBe(0)
        ->and($rider->fresh()?->hasRole(Role::Admin->value))->toBeTrue()
        ->and(Artisan::call('users:role', ['email' => 'boss@traceway.test', 'role' => 'user']))->toBe(1)
        ->and($boss->fresh()?->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Artisan::call('users:role', ['email' => 'nobody@traceway.test', 'role' => 'admin']))->toBe(1)
        ->and(Artisan::call('users:role', ['email' => 'rider@traceway.test', 'role' => 'owner']))->toBe(1);
});

it('gives every Super Admin each permission through the gate', function (): void {
    $boss = User::factory()->create();
    $boss->assignRole(Role::SuperAdmin->value);

    expect($boss->can('assign-roles'))->toBeTrue()
        ->and($boss->can('view-analytics'))->toBeTrue();
});

it('never leaves Traceway without a Super Admin, even when two writes race', function (): void {
    $first = User::factory()->create();
    $first->assignRole(Role::SuperAdmin->value);

    $second = User::factory()->create();
    $second->assignRole(Role::SuperAdmin->value);

    $userRepository = resolve(UserRepository::class);

    // Each handler checked while both were still Super Admins; the writes then land one after the other.
    $userRepository->assignRole(new RiderId($second->id), Role::User);

    expect(fn () => $userRepository->assignRole(new RiderId($first->id), Role::User))->toThrow(LastSuperAdmin::class)
        ->and(fn () => $userRepository->delete(new RiderId($first->id)))->toThrow(LastSuperAdmin::class)
        ->and($first->fresh()?->hasRole(Role::SuperAdmin->value))->toBeTrue();
});
