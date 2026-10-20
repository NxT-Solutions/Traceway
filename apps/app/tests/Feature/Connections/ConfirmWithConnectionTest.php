<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * A rider who signed up with Google and never set a password.
 */
function confirmWithConnectionRider(): User
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    $user = User::factory()->create(['password' => null]);
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    return $user;
}

function confirmWithConnectionGoogleAnswers(string $id): void
{
    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => $id, 'email' => 'ann@gmail.com', 'email_verified' => true])
        ->map(['id' => $id, 'name' => 'Ann Rider', 'email' => 'ann@gmail.com'])
        ->setApprovedScopes(['openid', 'email', 'profile']));
}

it('lets a rider without a password confirm it is them with the service they connected', function (): void {
    $this->freezeTime();
    $user = confirmWithConnectionRider();
    confirmWithConnectionGoogleAnswers('1001');

    $this->actingAs($user)->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->get(route('password.confirm.connection', ['provider' => 'google']))->assertRedirect('https://socialite.fake/google/authorize');
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('auth.password_confirmed_at', now()->getTimestamp());

    $this->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('settings/security'));
});

it('does not take a different account at the service as proof', function (): void {
    $user = confirmWithConnectionRider();
    confirmWithConnectionGoogleAnswers('9999');

    $this->actingAs($user)->get(route('password.confirm.connection', ['provider' => 'google']));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors(['connection' => 'That Google account is not the one connected to your Traceway account.'])
        ->assertSessionMissing('auth.password_confirmed_at');

    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
});

it('only confirms with a service the rider connected', function (): void {
    $user = confirmWithConnectionRider();
    config(['services.github.client_id' => 'github-id', 'services.github.client_secret' => 'github-secret']);

    $this->actingAs($user)
        ->get(route('password.confirm.connection', ['provider' => 'github']))
        ->assertNotFound();

    expect(session('connections.flow'))->toBeNull();
});

it('offers a rider without a password the connected services that are set up here', function (): void {
    $user = confirmWithConnectionRider();
    ConnectedAccount::factory()->for($user)->signInOnly('strava')->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/confirm-password')
            ->where('hasPassword', expected: false)
            ->where('connections', [['slug' => 'google', 'label' => 'Google']]));
});

it('asks a rider with a password for it, and offers no service in its place', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('hasPassword', expected: true)
            ->where('connections', []));
});

it('does not let a service stand in for the password of a rider who has one', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);
    confirmWithConnectionGoogleAnswers('1001');

    $this->actingAs($user)
        ->get(route('password.confirm.connection', ['provider' => 'google']))
        ->assertNotFound();

    expect(session('connections.flow'))->toBeNull();
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertSessionMissing('auth.password_confirmed_at');
    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
});

it('offers no service that cannot vouch for a rider yet', function (): void {
    config(['services.wahoo.client_id' => 'wahoo-id', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $user = confirmWithConnectionRider();
    ConnectedAccount::factory()->for($user)->withoutIdentity()->create();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('hasPassword', expected: false)
            ->where('connections', [['slug' => 'google', 'label' => 'Google']]));
    $this->get(route('password.confirm.connection', ['provider' => 'wahoo']))->assertNotFound();
});

/**
 * A rider without a password who signed up with Ride with GPS, whose keys never run out. Ride with GPS answers the
 * next round trip with a new key for this account.
 */
function confirmWithConnectionRideWithGpsRider(string $answers): RecordingCourseService
{
    config(['services.ride_with_gps.client_id' => 'rwgps-id', 'services.ride_with_gps.client_secret' => 'rwgps-secret']);
    $rideWithGps = new RecordingCourseService('ride_with_gps');
    app()->instance(CourseServices::class, new CourseServices(['ride_with_gps' => $rideWithGps]));

    Socialite::fake('ride_with_gps', (new SocialiteUser)
        ->map(['id' => $answers, 'name' => 'Road Rider', 'email' => 'road@rwgps.test'])
        ->setToken('access-new')
        ->setApprovedScopes([]));

    return $rideWithGps;
}

it('keeps the new key a service gave to confirm it is the rider, and lets go of the old one', function (): void {
    Mail::fake();
    $recordingCourseService = confirmWithConnectionRideWithGpsRider(answers: '7');
    $user = User::factory()->create(['password' => null]);
    ConnectedAccount::factory()->for($user)->create(['provider' => 'ride_with_gps', 'provider_user_id' => '7', 'access_token' => 'access-old', 'refresh_token' => null, 'expires_at' => null]);

    $this->actingAs($user)->get(route('password.confirm.connection', ['provider' => 'ride_with_gps']));
    $this->get(route('connections.callback', ['provider' => 'ride_with_gps', 'code' => 'from-rwgps', 'state' => 'state']))
        ->assertRedirect(route('connections.edit'))
        ->assertSessionHas('auth.password_confirmed_at');

    expect(ConnectedAccount::query()->sole()->access_token)->toBe('access-new')
        ->and($recordingCourseService->superseded)->toBe(['access-old'])
        ->and($recordingCourseService->revoked)->toBe([]);
    Mail::assertNotQueued(AccountMessage::class);
});

it('hands back the key a service gave for an account that is not the one connected', function (): void {
    $recordingCourseService = confirmWithConnectionRideWithGpsRider(answers: '9999');
    $user = User::factory()->create(['password' => null]);
    ConnectedAccount::factory()->for($user)->create(['provider' => 'ride_with_gps', 'provider_user_id' => '7', 'access_token' => 'access-old', 'refresh_token' => null, 'expires_at' => null]);

    $this->actingAs($user)->get(route('password.confirm.connection', ['provider' => 'ride_with_gps']));
    $this->get(route('connections.callback', ['provider' => 'ride_with_gps', 'code' => 'from-rwgps', 'state' => 'state']))
        ->assertRedirect(route('password.confirm'))
        ->assertSessionHasErrors('connection');

    expect($recordingCourseService->revoked)->toBe(['access-new'])
        ->and(ConnectedAccount::query()->sole()->access_token)->toBe('access-old');
});
