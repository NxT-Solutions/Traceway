<?php

declare(strict_types=1);

use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use App\Integrations\GarminConnect\GarminConnectAccess;
use App\Integrations\GarminConnect\GarminConnectConnector;
use App\Integrations\GarminConnect\GarminConnectTokenConnector;
use App\Integrations\GarminConnect\Requests\DeleteRegistrationRequest;
use Illuminate\Support\Facades\Date;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetRefreshTokenRequest;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Garmin Connect's access, with its token server and its partner API answering from mocks.
 */
function garminConnectAccess(?MockClient $tokens = null, ?MockClient $api = null): GarminConnectAccess
{
    $garminConnectTokenConnector = new GarminConnectTokenConnector('garmin-client', 'garmin-secret', 'https://garmin-auth.test/');
    $garminConnectTokenConnector->withMockClient($tokens ?? new MockClient([]));

    $garminConnectConnector = new GarminConnectConnector('https://garmin-api.test');
    $garminConnectConnector->withMockClient($api ?? new MockClient([]));

    return new GarminConnectAccess($garminConnectTokenConnector, $garminConnectConnector);
}

function garminConnectAccessTimeout(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Timed out'), $pendingRequest));
}

it('refreshes keys that ran out with its client keys, with the time the new ones run out', function (): void {
    $mock = new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 86400, 'token_type' => 'bearer']),
    ]);

    $serviceTokens = garminConnectAccess($mock)->refresh(new ServiceTokens('access', 'refresh', Date::now()->getTimestamp() - 60));

    expect($serviceTokens->accessToken)->toBe('access-2')
        ->and($serviceTokens->refreshToken)->toBe('refresh-2')
        ->and($serviceTokens->expiresAt)->toBeGreaterThan(Date::now()->getTimestamp() + 86_000)
        ->and($serviceTokens->expiresAt)->toBeLessThanOrEqual(Date::now()->getTimestamp() + 86_401);
    $mock->assertSent(fn (GetRefreshTokenRequest $getRefreshTokenRequest, Response $response): bool => $response->getPendingRequest()->body()?->all() === [
        'grant_type' => 'refresh_token',
        'refresh_token' => 'refresh',
        'client_id' => 'garmin-client',
        'client_secret' => 'garmin-secret',
    ] && $response->getPendingRequest()->getUrl() === 'https://garmin-auth.test/di-oauth2-service/oauth/token'
        && $response->getPendingRequest()->getMethod()->value === 'POST');
});

it('asks to connect again when Garmin will not refresh the keys', function (): void {
    garminConnectAccess(new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]))->refresh(new ServiceTokens('access', 'refresh'));
})->throws(ServiceRefused::class);

it('asks to connect again when Garmin gave no refresh token', function (): void {
    $mock = new MockClient([]);

    expect(fn (): ServiceTokens => garminConnectAccess($mock)->refresh(new ServiceTokens('access')))->toThrow(ServiceRefused::class);
    $mock->assertNothingSent();
});

it('says Garmin is unavailable when the refresh fails to arrive or comes back empty', function (MockResponse $mockResponse): void {
    garminConnectAccess(new MockClient([
        GetRefreshTokenRequest::class => $mockResponse,
    ]))->refresh(new ServiceTokens('access', 'refresh'));
})->with([
    'a timeout' => fn (): MockResponse => MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Timed out'), $pendingRequest)),
    'no token' => fn (): MockResponse => MockResponse::make(['token_type' => 'bearer']),
])->throws(ServiceUnavailable::class);

it('says Garmin is unavailable, not that the keys are dead, when its refresh fails on its side or is too busy', function (int $status): void {
    garminConnectAccess(new MockClient([
        GetRefreshTokenRequest::class => MockResponse::make(['error' => 'server_error'], $status),
    ]))->refresh(new ServiceTokens('access', 'refresh'));
})->with([
    'a server error' => [500],
    'down for upkeep' => [503],
    'too many requests' => [429],
])->throws(ServiceUnavailable::class);

it('ends the rider registration at Garmin with their key', function (): void {
    $mock = new MockClient([
        DeleteRegistrationRequest::class => MockResponse::make([], 204),
    ]);

    garminConnectAccess(api: $mock)->revoke(new ServiceTokens('access'));

    $mock->assertSent(fn (DeleteRegistrationRequest $deleteRegistrationRequest, Response $response): bool => $response->getPendingRequest()->getMethod()->value === 'DELETE'
        && $response->getPendingRequest()->getUrl() === 'https://garmin-api.test/wellness-api/rest/user/registration'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer access');
});

it('asks to connect again when Garmin no longer accepts the key', function (): void {
    garminConnectAccess(api: new MockClient([
        DeleteRegistrationRequest::class => MockResponse::make([], 401),
    ]))->revoke(new ServiceTokens('access'));
})->throws(ServiceRefused::class);

it('says Garmin is unavailable when it cannot be reached to end the registration', function (): void {
    garminConnectAccess(api: new MockClient([
        DeleteRegistrationRequest::class => garminConnectAccessTimeout(),
    ]))->revoke(new ServiceTokens('access'));
})->throws(ServiceUnavailable::class);

it('lets the rider go when Garmin fails to end the registration in another way', function (): void {
    $mock = new MockClient([
        DeleteRegistrationRequest::class => MockResponse::make([], 500),
    ]);

    garminConnectAccess(api: $mock)->revoke(new ServiceTokens('access'));

    $mock->assertSentCount(1);
});

it('leaves keys a new log in replaced alone, because ending the registration would stop the new ones too', function (): void {
    $tokens = new MockClient([]);
    $api = new MockClient([]);

    garminConnectAccess($tokens, $api)->supersede(new ServiceTokens('access-old', 'refresh-old'));

    $tokens->assertNothingSent();
    $api->assertNothingSent();
});
