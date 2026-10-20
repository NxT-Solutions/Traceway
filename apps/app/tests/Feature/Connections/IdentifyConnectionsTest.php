<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceAccount;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Sets up Wahoo and Ride with GPS as services that say whose account a key belongs to.
 *
 * @return array{wahoo: RecordingCourseService, ride_with_gps: RecordingCourseService}
 */
function identifyConnectionsServices(): array
{
    config([
        'services.wahoo.client_id' => 'wahoo-client',
        'services.wahoo.client_secret' => 'wahoo-secret',
        'services.ride_with_gps.client_id' => 'rwgps-client',
        'services.ride_with_gps.client_secret' => 'rwgps-secret',
    ]);

    $services = [
        'wahoo' => new RecordingCourseService('wahoo'),
        'ride_with_gps' => new RecordingCourseService('ride_with_gps'),
    ];

    app()->instance(CourseServices::class, new CourseServices($services));

    return $services;
}

/**
 * A connection carried over from before connections had the service's number.
 *
 * @param  array<string, mixed>  $attributes
 */
function identifyConnectionsCarriedOver(User $user, string $provider, string $accessToken, array $attributes = []): ConnectedAccount
{
    return ConnectedAccount::factory()->for($user)->withoutIdentity()->create([
        'provider' => $provider,
        'access_token' => $accessToken,
        'connected_at' => now()->subYear(),
        ...$attributes,
    ]);
}

it('gives each Wahoo and Ride with GPS connection from before the number of its account, and says how many', function (): void {
    $services = identifyConnectionsServices();
    $services['wahoo']->accounts['ann-wahoo'] = new ServiceAccount('60462', 'ann@wahoo.test');
    $services['ride_with_gps']->accounts['bob-rwgps'] = new ServiceAccount('4711');
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'ann-wahoo');
    $rideWithGps = identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'bob-rwgps');
    $connectedAt = $connectedAccount->connected_at->getTimestamp();

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 2 of 2 Wahoo and Ride with GPS connections from before. 0 need connecting again, 0 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    $connectedAccount->refresh();
    $rideWithGps->refresh();
    expect($connectedAccount->provider_user_id)->toBe('60462')
        ->and($connectedAccount->account_label)->toBe('ann@wahoo.test')
        ->and($connectedAccount->connected_at->getTimestamp())->toBe($connectedAt)
        ->and($connectedAccount->access_token)->toBe('ann-wahoo')
        ->and($rideWithGps->provider_user_id)->toBe('4711')
        ->and($rideWithGps->account_label)->toBeNull()
        ->and($services['wahoo']->asked)->toBe(['ann-wahoo'])
        ->and($services['ride_with_gps']->asked)->toBe(['bob-rwgps']);
});

it('lets the rider log in with a Wahoo connection from before once it has its number', function (): void {
    identifyConnectionsServices();
    $user = User::factory()->create(['email' => 'ann@example.com']);
    identifyConnectionsCarriedOver($user, 'wahoo', 'ann-wahoo');
    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Ann Rider', 'email' => 'ann@wahoo.test'])
        ->setToken('ann-wahoo')
        ->setRefreshToken('refresh-abc')
        ->setExpiresIn(7200));

    $this->artisan('connections:identify')->assertSuccessful();

    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1);
});

it('leaves a connection alone that has its number already', function (): void {
    $services = identifyConnectionsServices();
    $connectedAccount = ConnectedAccount::factory()->create(['provider_user_id' => '777', 'access_token' => 'ann-wahoo']);

    $this->artisan('connections:identify')
        ->expectsOutputToContain('Found the account of 0 of 0 Wahoo and Ride with GPS connections from before.')
        ->assertSuccessful();

    expect($connectedAccount->refresh()->provider_user_id)->toBe('777')
        ->and($services['wahoo']->asked)->toBe([]);
});

it('refreshes keys that ran out before asking, and keeps the new ones', function (): void {
    $services = identifyConnectionsServices();
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'stale', ['expires_at' => now()->subHour()]);

    $this->artisan('connections:identify')->assertSuccessful();

    $connectedAccount->refresh();
    expect($services['wahoo']->refreshed)->toBe(1)
        ->and($services['wahoo']->asked)->toBe(['access-refreshed'])
        ->and($connectedAccount->access_token)->toBe('access-refreshed')
        ->and($connectedAccount->refresh_token)->toBe('refresh-refreshed')
        ->and($connectedAccount->provider_user_id)->toBe('60462');
});

it('forgets keys the service turns down and asks the rider to connect again', function (): void {
    $services = identifyConnectionsServices();
    $services['wahoo']->failure = 'refused';
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'ann-wahoo', ['refresh_token' => 'ann-refresh']);

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 0 of 1 Wahoo and Ride with GPS connections from before. 1 need connecting again, 0 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    $connectedAccount->refresh();
    expect($connectedAccount->needs_reconnect)->toBeTrue()
        ->and($connectedAccount->provider_user_id)->toBeNull()
        ->and($connectedAccount->access_token)->toBeNull()
        ->and($connectedAccount->refresh_token)->toBeNull()
        ->and($connectedAccount->expires_at)->toBeNull();
});

it('does not ask again about a connection that has to be connected again, or has no keys', function (array $attributes): void {
    $services = identifyConnectionsServices();
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'ann-wahoo', $attributes);

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 0 of 0 Wahoo and Ride with GPS connections from before. 0 need connecting again, 0 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    expect($services['wahoo']->asked)->toBe([])
        ->and($services['wahoo']->refreshed)->toBe(0)
        ->and($connectedAccount->refresh()->provider_user_id)->toBeNull();
})->with([
    'to be connected again' => [['needs_reconnect' => true]],
    'no keys' => [['access_token' => null, 'refresh_token' => null, 'expires_at' => null]],
]);

it('tries again next time when the service cannot be reached', function (): void {
    $services = identifyConnectionsServices();
    $services['ride_with_gps']->failure = 'unavailable';
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'bob-rwgps');

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 0 of 1 Wahoo and Ride with GPS connections from before. 0 need connecting again, 1 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    $connectedAccount->refresh();
    expect($connectedAccount->needs_reconnect)->toBeFalse()
        ->and($connectedAccount->provider_user_id)->toBeNull()
        ->and($connectedAccount->access_token)->toBe('bob-rwgps');
});

it('stops asking a service that cannot be reached for the rest of the run, and still asks the others', function (): void {
    $services = identifyConnectionsServices();
    $services['ride_with_gps']->failure = 'unavailable';
    identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'bob-rwgps', ['connected_at' => now()->subYears(2)]);
    identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'cat-rwgps');
    identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'dan-rwgps');
    identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'eve-rwgps');
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'ann-wahoo');

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 1 of 5 Wahoo and Ride with GPS connections from before. 0 need connecting again, 4 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    expect($services['ride_with_gps']->asked)->toBe(['bob-rwgps', 'cat-rwgps', 'dan-rwgps'])
        ->and($services['wahoo']->asked)->toBe(['ann-wahoo'])
        ->and($connectedAccount->refresh()->provider_user_id)->toBe('60462');
});

it("still asks the others when the service cannot find one rider's account", function (): void {
    $services = identifyConnectionsServices();
    $services['ride_with_gps']->unavailableFor = ['bob-rwgps'];
    $stuck = identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'bob-rwgps', ['connected_at' => now()->subYears(2)]);
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'cat-rwgps');

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 1 of 2 Wahoo and Ride with GPS connections from before. 0 need connecting again, 1 could not be asked, and 0 belong to another rider.')
        ->assertSuccessful();

    expect($services['ride_with_gps']->asked)->toBe(['bob-rwgps', 'cat-rwgps'])
        ->and($stuck->refresh()->provider_user_id)->toBeNull()
        ->and($stuck->needs_reconnect)->toBeFalse()
        ->and($connectedAccount->refresh()->provider_user_id)->toBe('60462');
});

it('emails each rider whose connection from before now logs them in, as for any new way in', function (): void {
    Mail::fake();
    $services = identifyConnectionsServices();
    $services['wahoo']->accounts['ann-wahoo'] = new ServiceAccount('60462');
    $services['wahoo']->accounts['bob-wahoo'] = new ServiceAccount('60462');
    identifyConnectionsCarriedOver(User::factory()->create(['name' => 'Ann', 'email' => 'ann@example.com']), 'wahoo', 'ann-wahoo', ['connected_at' => now()->subYears(2)]);
    // Bob's keys belong to the account Ann connected first, so it does not log Bob in, and Bob hears nothing.
    identifyConnectionsCarriedOver(User::factory()->create(['email' => 'bob@example.com']), 'wahoo', 'bob-wahoo');

    $this->artisan('connections:identify')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('ann@example.com')
        && $accountMessage->template === 'connection-added'
        && $accountMessage->values['service'] === 'Wahoo');
});

it('leaves an account another rider connected already without a number, asks to connect again, and reports it once', function (): void {
    Exceptions::fake();
    $services = identifyConnectionsServices();
    $services['wahoo']->accounts['ann-wahoo'] = new ServiceAccount('60462', 'shared@wahoo.test');
    $owner = User::factory()->create();
    ConnectedAccount::factory()->for($owner)->create(['provider_user_id' => '60462']);
    $rider = User::factory()->create();
    $connectedAccount = identifyConnectionsCarriedOver($rider, 'wahoo', 'ann-wahoo');

    $this->artisan('connections:identify')
        ->expectsOutput('Found the account of 0 of 1 Wahoo and Ride with GPS connections from before. 0 need connecting again, 0 could not be asked, and 1 belong to another rider.')
        ->assertSuccessful();

    expect($connectedAccount->refresh()->provider_user_id)->toBeNull()
        ->and($connectedAccount->needs_reconnect)->toBeTrue()
        ->and($connectedAccount->access_token)->toBe('ann-wahoo');
    Exceptions::assertReported(fn (RuntimeException $runtimeException): bool => $runtimeException->getMessage() === 'The Wahoo account of rider '.$rider->id.' is connected to rider '.$owner->id.' already, so it was left without a number.'
        && ! str_contains($runtimeException->getMessage(), 'shared@wahoo.test'));

    // A route sent with fresh keys brings the connection back to the next run.
    $connectedAccount->forceFill(['needs_reconnect' => false])->save();

    $this->artisan('connections:identify')
        ->expectsOutputToContain('and 1 belong to another rider.')
        ->assertSuccessful();

    Exceptions::assertReportedCount(1);
    expect($services['wahoo']->asked)->toBe(['ann-wahoo', 'ann-wahoo']);
});

it('writes the counts to the log, because the scheduler throws the output away', function (): void {
    Log::spy();
    identifyConnectionsServices();
    identifyConnectionsCarriedOver(User::factory()->create(), 'wahoo', 'ann-wahoo');

    $this->artisan('connections:identify')->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->with('Found the account of 1 of 1 Wahoo and Ride with GPS connections from before. 0 need connecting again, 0 could not be asked, and 0 belong to another rider.');
});

it('runs every night in the background on the production scheduler', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'connections:identify'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('40 4 * * *')
        ->and($events->first()?->runInBackground)->toBeTrue()
        ->and($events->first()?->withoutOverlapping)->toBeTrue();
});

it('does not ask a service that is not set up here', function (): void {
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));
    $connectedAccount = identifyConnectionsCarriedOver(User::factory()->create(), 'ride_with_gps', 'bob-rwgps');

    $this->artisan('connections:identify')->assertSuccessful();

    expect($connectedAccount->refresh()->provider_user_id)->toBeNull()
        ->and($wahoo->asked)->toBe([]);
});
