<?php

declare(strict_types=1);

use App\Domain\Courses\Services\ServiceRefused;
use App\Integrations\GarminConnect\Requests\DeleteRegistrationRequest;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetRefreshTokenRequest;
use Saloon\Http\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.garmin_connect.client_id' => 'garmin-client', 'services.garmin_connect.client_secret' => 'garmin-secret']);
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

/**
 * A rider with a password and Garmin Connect connected, with keys that work for another hour unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function garminDeregistrationRider(array $attributes = []): User
{
    $user = User::factory()->create(['password' => 'password']);

    ConnectedAccount::factory()->for($user)->create([
        'provider' => 'garmin_connect',
        'access_token' => 'garmin-access',
        'refresh_token' => 'garmin-refresh',
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);

    return $user;
}

/**
 * True when Traceway asked Garmin's partner API to end the registration with this key.
 */
function garminDeregistrationSent(MockClient $mockClient, string $accessToken): bool
{
    $sent = false;

    foreach ($mockClient->getRecordedResponses() as $response) {
        $pendingRequest = $response->getPendingRequest();

        if ($pendingRequest->getRequest() instanceof DeleteRegistrationRequest
            && $pendingRequest->getMethod()->value === 'DELETE'
            && $pendingRequest->getUrl() === 'https://apis.garmin.com/wellness-api/rest/user/registration'
            && $pendingRequest->headers()->get('Authorization') === 'Bearer '.$accessToken) {
            $sent = true;
        }
    }

    return $sent;
}

/**
 * The rider disconnects Garmin Connect, or deletes their account.
 */
function garminDeregistrationLeave(User $user, string $how): TestResponse
{
    $testCase = test()->actingAs($user);

    return $how === 'disconnect'
        ? $testCase->delete(route('connections.destroy', ['provider' => 'garmin_connect']))
        : $testCase->delete(route('profile.destroy'), ['password' => 'password']);
}

it('ends the registration at Garmin with the stored key when the rider disconnects Garmin Connect', function (): void {
    $mockClient = MockClient::global([
        DeleteRegistrationRequest::class => MockResponse::make([], 204),
    ]);
    $user = garminDeregistrationRider();

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'garmin_connect']))
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Garmin Connect disconnected.']);

    expect(garminDeregistrationSent($mockClient, 'garmin-access'))->toBeTrue()
        ->and(ConnectedAccount::query()->count())->toBe(0);
    $mockClient->assertSentCount(1);
});

it('ends the registration at Garmin when a rider with Garmin Connect deletes their account', function (): void {
    $mockClient = MockClient::global([
        DeleteRegistrationRequest::class => MockResponse::make([], 204),
    ]);
    $user = garminDeregistrationRider();

    garminDeregistrationLeave($user, 'delete the account')->assertRedirect();

    expect(garminDeregistrationSent($mockClient, 'garmin-access'))->toBeTrue();
    $this->assertModelMissing($user);
});

it('refreshes a Garmin key that ran out, then ends the registration with the new one', function (string $how): void {
    $mockClient = MockClient::global([
        GetRefreshTokenRequest::class => MockResponse::make(['access_token' => 'garmin-access-2', 'refresh_token' => 'garmin-refresh-2', 'expires_in' => 86400]),
        DeleteRegistrationRequest::class => MockResponse::make([], 204),
    ]);
    $user = garminDeregistrationRider(['expires_at' => now()->subDay()]);

    garminDeregistrationLeave($user, $how)->assertRedirect();

    $mockClient->assertSent(fn (GetRefreshTokenRequest $getRefreshTokenRequest, Response $response): bool => $response->getPendingRequest()->getUrl() === 'https://connectapi.garmin.com/di-oauth2-service/oauth/token'
        && ($response->getPendingRequest()->body()?->all()['refresh_token'] ?? null) === 'garmin-refresh');
    expect(garminDeregistrationSent($mockClient, 'garmin-access-2'))->toBeTrue()
        ->and(garminDeregistrationSent($mockClient, 'garmin-access'))->toBeFalse();
})->with(['disconnect', 'delete the account']);

it('reports, and still lets the rider go, when Garmin will no longer refresh the key', function (string $how): void {
    Exceptions::fake();
    $mockClient = MockClient::global([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]);
    $user = garminDeregistrationRider(['expires_at' => now()->subMonths(4)]);

    garminDeregistrationLeave($user, $how)->assertRedirect();

    expect(ConnectedAccount::query()->count())->toBe(0);
    $mockClient->assertNotSent(DeleteRegistrationRequest::class);
    Exceptions::assertReported(ServiceRefused::class);
})->with(['disconnect', 'delete the account']);
