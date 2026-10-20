<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Gives Traceway keys for these services, which turns them on.
 */
function disconnectWithServices(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        config(["services.{$slug}.client_id" => $slug.'-id', "services.{$slug}.client_secret" => $slug.'-secret']);
    }
}

function disconnectLinked(User $user, string $provider): ConnectedAccount
{
    return ConnectedAccount::factory()->for($user)->signInOnly($provider)->create();
}

it('disconnects a service, hands back its keys, forgets the routes sent through it, and tells the rider', function (): void {
    Mail::fake();
    disconnectWithServices('ride_with_gps');
    $rideWithGps = new RecordingCourseService('ride_with_gps');
    app()->instance(CourseServices::class, new CourseServices(['ride_with_gps' => $rideWithGps]));
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->create(['provider' => 'ride_with_gps', 'access_token' => 'rwgps-token']);
    $savedRoute = SavedRoute::factory()->for($user)->create();
    DB::table('sent_routes')->insert([
        ['user_id' => $user->id, 'saved_route_id' => $savedRoute->id, 'leg' => 0, 'service' => 'ride_with_gps', 'external_id' => '8', 'sent_at' => now()],
        ['user_id' => $user->id, 'saved_route_id' => $savedRoute->id, 'leg' => 0, 'service' => 'wahoo', 'external_id' => '7', 'sent_at' => now()],
    ]);

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'ride_with_gps']))
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Ride with GPS disconnected.']);

    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and($rideWithGps->revoked)->toBe(['rwgps-token'])
        ->and(DB::table('sent_routes')->pluck('service')->all())->toBe(['wahoo']);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-removed'
        && $accountMessage->hasTo($user->email)
        && $accountMessage->values['service'] === 'Ride with GPS');
});

it('keeps the last way a rider without a password can log in', function (array $others): void {
    Mail::fake();
    disconnectWithServices('google');
    $user = User::factory()->create(['password' => null]);
    $connectedAccount = disconnectLinked($user, 'google');

    foreach ($others as $other) {
        disconnectLinked($user, $other);
    }

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'google']))
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Add a password or a passkey first, so you can still log in.']);

    $this->assertModelExists($connectedAccount);
    Mail::assertNothingQueued();
})->with([
    'no other way in' => [[]],
    'only a service that is no longer set up' => [['discord']],
]);

it('lets a rider without a password disconnect while another service still logs them in', function (): void {
    Mail::fake();
    disconnectWithServices('google', 'github');
    $user = User::factory()->create(['password' => null]);
    $connectedAccount = disconnectLinked($user, 'google');
    $github = disconnectLinked($user, 'github');

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'google']))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertModelMissing($connectedAccount);
    $this->assertModelExists($github);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-removed');
});

it('lets a rider without a password disconnect while a passkey still logs them in', function (): void {
    disconnectWithServices('google');
    $user = User::factory()->create(['password' => null]);
    $connectedAccount = disconnectLinked($user, 'google');
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Phone',
        'credential_id' => 'credential-1',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'google']))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertModelMissing($connectedAccount);
});

it('disconnects a service that is no longer set up here', function (): void {
    $user = User::factory()->create();
    $connectedAccount = disconnectLinked($user, 'discord');

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'discord']))
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Discord disconnected.']);

    $this->assertModelMissing($connectedAccount);
});

it('changes nothing for a service the rider never connected', function (): void {
    Mail::fake();
    $connectedAccount = disconnectLinked(User::factory()->create(), 'google');
    $user = User::factory()->create();
    $github = disconnectLinked($user, 'github');

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'google']))
        ->assertRedirect(route('connections.edit'));

    $this->assertModelExists($connectedAccount);
    $this->assertModelExists($github);
    Mail::assertNothingQueued();
});
