<?php

declare(strict_types=1);

use App\Domain\Connections\GrantedAccesses;
use App\Models\ConnectedAccount;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetRefreshTokenRequest;
use Saloon\Http\Response;
use Tests\Fakes\RecordingGrantedAccess;

uses(RefreshDatabase::class);

afterEach(function (): void {
    MockClient::destroyGlobal();
});

/**
 * Garmin Connect, set up as access that remembers what Traceway asked of it.
 */
function keepConnectionAccessGarmin(): RecordingGrantedAccess
{
    $recordingGrantedAccess = new RecordingGrantedAccess;
    app()->instance(GrantedAccesses::class, new GrantedAccesses(['garmin_connect' => $recordingGrantedAccess]));

    return $recordingGrantedAccess;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function keepConnectionAccessLinked(string $accessToken, array $attributes = []): ConnectedAccount
{
    return ConnectedAccount::factory()->create([
        'provider' => 'garmin_connect',
        'access_token' => $accessToken,
        'refresh_token' => 'refresh-'.$accessToken,
        'expires_at' => now()->subDays(30),
        ...$attributes,
    ]);
}

it('refreshes and keeps the Garmin Connect keys of every rider who connected it, and says how many', function (): void {
    $recordingGrantedAccess = keepConnectionAccessGarmin();
    $connectedAccount = keepConnectionAccessLinked('ann-garmin');
    $bob = keepConnectionAccessLinked('bob-garmin', ['needs_reconnect' => true]);
    $google = ConnectedAccount::factory()->signInOnly('google')->create();

    $this->artisan('connections:keep-access')
        ->expectsOutput('Refreshed the keys of 2 of 2 Garmin Connect connections. 0 need connecting again, and 0 could not be asked.')
        ->assertSuccessful();

    expect($recordingGrantedAccess->refreshed)->toEqualCanonicalizing(['ann-garmin', 'bob-garmin'])
        ->and($recordingGrantedAccess->revoked)->toBe([])
        ->and($connectedAccount->refresh()->access_token)->toBe('access-refreshed')
        ->and($connectedAccount->refresh_token)->toBe('refresh-refreshed')
        ->and($bob->refresh()->access_token)->toBe('access-refreshed')
        ->and($bob->needs_reconnect)->toBeFalse()
        ->and($google->refresh()->access_token)->toBeNull();
});

it('asks the rider to connect again when Garmin turns the keys down, and tries again next week when it cannot be reached', function (): void {
    $recordingGrantedAccess = keepConnectionAccessGarmin();
    $recordingGrantedAccess->failures = ['dead' => 'refused', 'down' => 'unavailable'];

    $connectedAccount = keepConnectionAccessLinked('dead');
    $down = keepConnectionAccessLinked('down');

    $this->artisan('connections:keep-access')
        ->expectsOutput('Refreshed the keys of 0 of 2 Garmin Connect connections. 1 need connecting again, and 1 could not be asked.')
        ->assertSuccessful();

    expect($connectedAccount->refresh()->needs_reconnect)->toBeTrue()
        ->and($connectedAccount->access_token)->toBe('dead')
        ->and($down->refresh()->needs_reconnect)->toBeFalse()
        ->and($down->access_token)->toBe('down');
});

it('asks nothing when Garmin Connect is not set up here', function (): void {
    app()->instance(GrantedAccesses::class, new GrantedAccesses([]));
    $connectedAccount = keepConnectionAccessLinked('ann-garmin');

    $this->artisan('connections:keep-access')
        ->expectsOutput('Refreshed the keys of 0 of 0 Garmin Connect connections. 0 need connecting again, and 0 could not be asked.')
        ->assertSuccessful();

    expect($connectedAccount->refresh()->access_token)->toBe('ann-garmin');
});

it("refreshes the keys at Garmin's token server with Traceway's client keys", function (): void {
    config(['services.garmin_connect.client_id' => 'garmin-client', 'services.garmin_connect.client_secret' => 'garmin-secret']);
    $mockClient = MockClient::global([
        GetRefreshTokenRequest::class => MockResponse::make(['access_token' => 'garmin-access-2', 'refresh_token' => 'garmin-refresh-2', 'expires_in' => 86400]),
    ]);
    $connectedAccount = keepConnectionAccessLinked('garmin-access', ['refresh_token' => 'garmin-refresh']);

    $this->artisan('connections:keep-access')->assertSuccessful();

    $mockClient->assertSent(fn (GetRefreshTokenRequest $getRefreshTokenRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://connectapi.garmin.com/di-oauth2-service/oauth/token'
        && ($response->getPendingRequest()->body()?->all()['refresh_token'] ?? null) === 'garmin-refresh');
    expect($connectedAccount->refresh()->access_token)->toBe('garmin-access-2')
        ->and($connectedAccount->refresh_token)->toBe('garmin-refresh-2')
        ->and($connectedAccount->expires_at?->isFuture())->toBeTrue();
});

it("does not ask the rider to connect again when Garmin's token server fails on its side", function (): void {
    config(['services.garmin_connect.client_id' => 'garmin-client', 'services.garmin_connect.client_secret' => 'garmin-secret']);
    MockClient::global([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'server_error'], 503),
    ]);
    $connectedAccount = keepConnectionAccessLinked('garmin-access', ['refresh_token' => 'garmin-refresh']);

    $this->artisan('connections:keep-access')
        ->expectsOutput('Refreshed the keys of 0 of 1 Garmin Connect connections. 0 need connecting again, and 1 could not be asked.')
        ->assertSuccessful();

    expect($connectedAccount->refresh()->needs_reconnect)->toBeFalse()
        ->and($connectedAccount->access_token)->toBe('garmin-access')
        ->and($connectedAccount->refresh_token)->toBe('garmin-refresh');
});

it('writes the counts to the log, because the scheduler throws the output away', function (): void {
    Log::spy();
    keepConnectionAccessGarmin()->failures = ['dead' => 'refused'];
    keepConnectionAccessLinked('ann-garmin');
    keepConnectionAccessLinked('dead');

    $this->artisan('connections:keep-access')->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->with('Refreshed the keys of 1 of 2 Garmin Connect connections. 1 need connecting again, and 0 could not be asked.');
});

it('runs every week in the background on the production scheduler', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'connections:keep-access'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('20 4 * * 1')
        ->and($events->first()?->runInBackground)->toBeTrue()
        ->and($events->first()?->withoutOverlapping)->toBeTrue();
});
