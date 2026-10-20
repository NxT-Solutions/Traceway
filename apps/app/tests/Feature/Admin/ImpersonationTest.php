<?php

declare(strict_types=1);

use App\Account\Role;
use App\Auth\Impersonation;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function riderAs(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('lets a Super Admin see Traceway as a rider and come back', function (): void {
    $user = riderAs(Role::SuperAdmin);
    $rider = riderAs(Role::User);

    $this->actingAs($user)
        ->post(route('admin.users.impersonate', $rider->id))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($rider);
    expect(session(Impersonation::SESSION_KEY))->toBe($user->id);

    $this->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('impersonating.name', $rider->name)
            ->where('auth.can', expected: null));

    $this->delete(route('admin.impersonation.destroy'))
        ->assertRedirect(route('admin.users.show', $rider->id));

    $this->assertAuthenticatedAs($user);
    expect(session(Impersonation::SESSION_KEY))->toBeNull()
        ->and(DB::table('admin_actions')->orderBy('id')->pluck('action')->all())->toBe(['impersonation_started', 'impersonation_stopped'])
        ->and(DB::table('admin_actions')->where('rider_id', $rider->id)->where('actor_id', $user->id)->count())->toBe(2);
});

it("keeps the rider's sign-in and the admin area closed while acting as them", function (string $method, string $path): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $user = riderAs(Role::SuperAdmin);
    $rider = riderAs(Role::Admin);
    $rider->forceFill(['two_factor_secret' => 'kept'])->save();
    $connectedAccount = ConnectedAccount::factory()->for($rider)->signInOnly('google')->create();

    $this->actingAs($user)->post(route('admin.users.impersonate', $rider->id));

    $this->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->call($method, $path, ['password' => 'password', 'current_password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($rider);
    $this->assertModelExists($connectedAccount);
    expect(User::query()->find($rider->id)?->two_factor_secret)->toBe('kept')
        ->and(session('connections.flow'))->toBeNull();
})->with([
    'the admin area' => ['GET', '/admin'],
    'an admin write' => ['DELETE', '/admin/users/1/sessions'],
    'the security page' => ['GET', '/settings/security'],
    'turning two-factor off' => ['DELETE', '/user/two-factor-authentication'],
    'the recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
    'changing the password' => ['PUT', '/settings/password'],
    'deleting the account' => ['DELETE', '/settings/profile'],
    'emailing the data copy' => ['GET', '/settings/privacy/export'],
    'connecting a service' => ['GET', '/settings/connections/google/connect'],
    'disconnecting a service' => ['DELETE', '/settings/connections/google'],
    'confirming with a service' => ['GET', '/user/confirm-password/google'],
    'a service answering' => ['GET', '/auth/google/callback'],
    'confirming a new email' => ['GET', '/settings/profile/email/1/hash'],
    'sending a new email link again' => ['POST', '/settings/profile/email'],
    'keeping the old email' => ['DELETE', '/settings/profile/email'],
]);

it("lets a Super Admin acting as a rider look at the rider's connections", function (): void {
    $user = riderAs(Role::SuperAdmin);
    $rider = riderAs(Role::User);

    $this->actingAs($user)->post(route('admin.users.impersonate', $rider->id));

    $this->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('settings/connections'));
});

it('lets only a Super Admin act as someone, and never as another Super Admin or themselves', function (): void {
    $user = riderAs(Role::Admin);
    $boss = riderAs(Role::SuperAdmin);
    $otherBoss = riderAs(Role::SuperAdmin);
    $rider = riderAs(Role::User);

    $this->actingAs($user)->post(route('admin.users.impersonate', $rider->id))->assertForbidden();
    $this->actingAs($boss)->post(route('admin.users.impersonate', $otherBoss->id))->assertInertiaFlash('toast.type', 'error');
    $this->actingAs($boss)->post(route('admin.users.impersonate', $boss->id))->assertInertiaFlash('toast.type', 'error');

    $this->assertAuthenticatedAs($boss);
    expect(DB::table('admin_actions')->count())->toBe(0);
});

it('does not count acting as a rider as the rider using Traceway', function (): void {
    $user = riderAs(Role::SuperAdmin);
    $rider = riderAs(Role::User);

    $this->actingAs($user)->post(route('admin.users.impersonate', $rider->id));
    $this->get(route('profile.edit'))->assertOk();

    expect($rider->fresh()?->last_seen_at)->toBeNull()
        ->and(DB::table('usage_counters')->where('metric', 'sign_ins')->sum('count'))->toBe(0);
});
