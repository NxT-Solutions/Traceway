<?php

declare(strict_types=1);

use App\Account\Role;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\SavedRoute;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
});

function adminRider(Role $role = Role::Admin): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('lists riders with their role and finds them by name or email', function (): void {
    $user = adminRider();
    $rider = User::factory()->create(['name' => 'Gravel Grace', 'email' => 'grace@traceway.test']);
    $rider->assignRole(Role::User->value);
    SavedRoute::factory()->for($rider)->create();
    User::factory()->create(['name' => 'Someone else']);

    $this->actingAs($user)
        ->get(route('admin.users.index', ['search' => 'GRACE']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('admin/users/index')
            ->where('users.total', 1)
            ->where('users.users.0.email', 'grace@traceway.test')
            ->where('users.users.0.role', 'user')
            ->where('users.users.0.routes', 1));

    $this->actingAs($user)
        ->get(route('admin.users.index', ['role' => 'admin']))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('users.total', 1)
            ->where('users.users.0.id', $user->id));
});

it('shows one rider without secrets', function (): void {
    $rider = User::factory()->withTwoFactor()->create();

    $this->actingAs(adminRider())
        ->get(route('admin.users.show', $rider->id))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('admin/users/show')
            ->where('user.two_factor', expected: true)
            ->where('can.manage', expected: true)
            ->where('can.assignRole', expected: false)
            ->missing('user.two_factor_secret')
            ->missing('user.password'));
});

it("lists the services a rider connected, without the service's keys", function (): void {
    $rider = User::factory()->create();
    ConnectedAccount::factory()->for($rider)->create(['access_token' => 'wahoo-access-token', 'refresh_token' => 'wahoo-refresh-token', 'last_sent_at' => now()]);
    ConnectedAccount::factory()->for($rider)->signInOnly('google')->create(['last_signed_in_at' => now()]);

    $this->actingAs(adminRider())
        ->get(route('admin.users.show', $rider->id))
        ->assertOk()
        ->assertDontSee('wahoo-access-token')
        ->assertDontSee('wahoo-refresh-token')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->has('user.services', 2)
            ->has('user.services.0', fn (AssertableJson $assertableJson): AssertableJson => $assertableJson
                ->where('service', 'Google')
                ->whereType('connected_at', 'string')
                ->whereType('last_signed_in_at', 'string')
                ->where('last_sent_at', expected: null))
            ->has('user.services.1', fn (AssertableJson $assertableJson): AssertableJson => $assertableJson
                ->where('service', 'Wahoo')
                ->whereType('connected_at', 'string')
                ->where('last_signed_in_at', expected: null)
                ->whereType('last_sent_at', 'string')));
});

it('opens an account for someone and sends them a link to choose a password', function (): void {
    $this->actingAs(adminRider())
        ->post(route('admin.users.store'), ['name' => 'Hike Hannah', 'email' => 'hannah@traceway.test'])
        ->assertRedirect();

    $user = User::query()->where('email', 'hannah@traceway.test')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([Role::User->value]);
    Notification::assertSentTo($user, ResetPassword::class);
});

it("changes a rider's name and can confirm the email", function (): void {
    $rider = User::factory()->unverified()->create();

    $this->actingAs(adminRider())
        ->patch(route('admin.users.update', $rider->id), [
            'name' => 'Renamed',
            'email' => $rider->email,
            'confirm_email' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect($rider->fresh()?->name)->toBe('Renamed')
        ->and($rider->fresh()?->email_verified_at)->not->toBeNull()
        ->and($rider->fresh()?->welcomed_at)->not->toBeNull();
});

it('lets a Super Admin give a role, but not take the last Super Admin', function (): void {
    $user = adminRider(Role::SuperAdmin);
    $rider = User::factory()->create();
    $rider->assignRole(Role::User->value);

    $this->actingAs($user)
        ->put(route('admin.users.role', $rider->id), ['role' => 'admin'])
        ->assertInertiaFlash('toast.type', 'success');

    expect($rider->fresh()?->getRoleNames()->all())->toBe([Role::Admin->value]);

    $this->actingAs($user)
        ->put(route('admin.users.role', $user->id), ['role' => 'user'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($user->fresh()?->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('keeps an Admin away from a Super Admin and from their own account', function (): void {
    $user = adminRider();
    $boss = adminRider(Role::SuperAdmin);

    $this->actingAs($user)
        ->delete(route('admin.users.destroy', $boss->id))
        ->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($user)
        ->delete(route('admin.users.destroy', $user->id))
        ->assertInertiaFlash('toast.type', 'error');

    expect(User::query()->whereKey([$user->id, $boss->id])->count())->toBe(2);
});

it('turns two-factor off and signs a rider out everywhere', function (): void {
    $rider = User::factory()->withTwoFactor()->create(['remember_token' => 'old-token']);
    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $rider->id,
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);
    $user = adminRider();

    $this->actingAs($user)->delete(route('admin.users.two-factor.destroy', $rider->id));
    $this->actingAs($user)->delete(route('admin.users.sessions.destroy', $rider->id));

    $fresh = $rider->fresh();
    expect($fresh?->two_factor_secret)->toBeNull()
        ->and($fresh?->two_factor_confirmed_at)->toBeNull()
        ->and($fresh?->remember_token)->not->toBe('old-token')
        ->and(DB::table('sessions')->where('user_id', $rider->id)->count())->toBe(0);
});

it('deletes a rider the same way they would delete themselves', function (): void {
    $rider = User::factory()->create();
    SavedRoute::factory()->for($rider)->create();

    $this->actingAs(adminRider())
        ->delete(route('admin.users.destroy', $rider->id))
        ->assertRedirect(route('admin.users.index'));

    expect(User::query()->find($rider->id))->toBeNull()
        ->and(SavedRoute::query()->where('user_id', $rider->id)->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $rider->id)->count())->toBe(0);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo($rider->email));
});
