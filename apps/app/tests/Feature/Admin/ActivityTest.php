<?php

declare(strict_types=1);

use App\Account\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
});

function activityRider(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it("records who changed whose role, and shows it on the activity page and the rider's page", function (): void {
    $user = activityRider(Role::SuperAdmin);
    $rider = activityRider(Role::User);

    $this->actingAs($user)->put(route('admin.users.role', $rider->id), ['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('admin.activity'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('admin/activity')
            ->where('activity.total', 1)
            ->where('activity.entries.0.summary', 'Changed the role from User to Admin')
            ->where('activity.entries.0.actor.id', $user->id)
            ->where('activity.entries.0.rider.id', $rider->id));

    $this->actingAs($user)
        ->get(route('admin.users.show', $rider->id))
        ->assertInertia(fn (Assert $assert): Assert => $assert->has('activity', 1));
});

it('records each admin write', function (): void {
    $user = activityRider(Role::Admin);
    $rider = User::factory()->unverified()->withTwoFactor()->create();

    $this->actingAs($user)->patch(route('admin.users.update', $rider->id), ['name' => 'Renamed', 'email' => $rider->email, 'confirm_email' => '1']);
    $this->actingAs($user)->delete(route('admin.users.two-factor.destroy', $rider->id));
    $this->actingAs($user)->delete(route('admin.users.sessions.destroy', $rider->id));
    $this->actingAs($user)->post(route('admin.users.store'), ['name' => 'New', 'email' => 'new@traceway.test']);

    expect(DB::table('admin_actions')->orderBy('id')->pluck('action')->all())
        ->toBe(['updated', 'confirmed_email', 'two_factor_reset', 'signed_out', 'created']);
});

it('keeps no trace of a deleted rider in the log', function (): void {
    $user = activityRider(Role::Admin);
    $rider = activityRider(Role::User);

    $this->actingAs($user)->delete(route('admin.users.two-factor.destroy', $rider->id));
    $this->actingAs($user)->delete(route('admin.users.destroy', $rider->id));

    expect(DB::table('admin_actions')->whereNotNull('rider_id')->count())->toBe(0)
        ->and(DB::table('admin_actions')->where('action', 'deleted')->count())->toBe(1);
});

it('records a role given from the server, and forgets entries after a year', function (): void {
    $rider = User::factory()->create(['email' => 'rider@traceway.test']);
    DB::table('admin_actions')->insert(['action' => 'created', 'created_at' => now()->subYears(2)]);

    Artisan::call('users:role', ['email' => 'rider@traceway.test', 'role' => 'admin']);
    Artisan::call('admin:prune-log');

    expect(DB::table('admin_actions')->count())->toBe(1)
        ->and(DB::table('admin_actions')->value('actor_id'))->toBeNull()
        ->and(DB::table('admin_actions')->value('rider_id'))->toBe($rider->id);
});

it("puts what was done to the account in the rider's copy of their data", function (): void {
    $user = activityRider(Role::Admin);
    $rider = activityRider(Role::User);

    $this->actingAs($user)->delete(route('admin.users.sessions.destroy', $rider->id));

    $response = $this->actingAs($rider)->get(route('privacy.export'));
    $document = json_decode((string) $response->streamedContent(), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($document['admin_actions'][0]['action'] ?? null)->toBe('Signed out everywhere');
});
