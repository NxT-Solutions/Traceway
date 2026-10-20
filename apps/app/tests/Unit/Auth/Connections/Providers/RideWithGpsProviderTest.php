<?php

declare(strict_types=1);

use App\Auth\Connections\Providers\RideWithGpsProvider;
use GuzzleHttp\Psr7\Response;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

function rideWithGpsSignIn(ScriptedSignIn $scriptedSignIn, ?string $apiKey = 'rwgps-api-key'): RideWithGpsProvider
{
    config(['services.ride_with_gps' => [
        'client_id' => 'rwgps-client',
        'client_secret' => 'rwgps-secret',
        'api_key' => $apiKey,
        'redirect' => 'https://traceway.test/settings/services/ride_with_gps/callback',
        'url' => 'https://rwgps.test',
    ]]);

    return $scriptedSignIn->provider('ride_with_gps', RideWithGpsProvider::class);
}

/**
 * @param  array<string, mixed>  $body
 */
function rideWithGpsSignInAnswer(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

it('sends the rider to Ride with GPS without asking for scopes', function (): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(rideWithGpsSignIn($scriptedSignIn));

    expect($authorize['url'])->toBe('https://rwgps.test/oauth/authorize')
        ->and($authorize['query'])->toBe([
            'client_id' => 'rwgps-client',
            'redirect_uri' => 'https://traceway.test/settings/services/ride_with_gps/callback',
            'response_type' => 'code',
            'state' => $scriptedSignIn->request->session()->get('state'),
        ]);
});

it('trades the code for a key as JSON, then reads the rider with it and the API key', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        rideWithGpsSignInAnswer(['access_token' => 'access-abc', 'token_type' => 'bearer']),
        rideWithGpsSignInAnswer(['user' => ['id' => 4711, 'name' => 'Road Rider', 'display_name' => 'roadrider', 'email' => 'road@rwgps.test']]),
    ]);

    $user = rideWithGpsSignIn($scriptedSignIn)->user();

    $request = $scriptedSignIn->guzzle->request(0);
    $account = $scriptedSignIn->guzzle->request(1);

    expect((string) $request->getUri())->toBe('https://rwgps.test/oauth/token.json')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $request->getBody(), associative: true))->toBe([
            'grant_type' => 'authorization_code',
            'client_id' => 'rwgps-client',
            'client_secret' => 'rwgps-secret',
            'code' => 'the-code',
            'redirect_uri' => 'https://traceway.test/settings/services/ride_with_gps/callback',
        ])
        ->and((string) $account->getUri())->toBe('https://rwgps.test/api/v1/users/current.json')
        ->and($account->getHeaderLine('Authorization'))->toBe('Bearer access-abc')
        ->and($account->getHeaderLine('x-rwgps-api-key'))->toBe('rwgps-api-key')
        ->and($user->getId())->toBe('4711')
        ->and($user->getName())->toBe('Road Rider')
        ->and($user->getNickname())->toBe('roadrider')
        ->and($user->getEmail())->toBe('road@rwgps.test')
        ->and($user->token)->toBe('access-abc')
        ->and($user->refreshToken)->toBeNull()
        ->and($user->expiresIn)->toBeNull();
});

it('reads the rider without an API key when none is set', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        rideWithGpsSignInAnswer(['access_token' => 'access-abc']),
        rideWithGpsSignInAnswer(['user' => ['id' => '4711']]),
    ]);

    rideWithGpsSignIn($scriptedSignIn, apiKey: null)->user();

    expect($scriptedSignIn->guzzle->request(1)->hasHeader('x-rwgps-api-key'))->toBeFalse();
});

it('names the rider by their display name, or their first and last name', function (array $account, string $name): void {
    $scriptedSignIn = new ScriptedSignIn([
        rideWithGpsSignInAnswer(['access_token' => 'access-abc']),
        rideWithGpsSignInAnswer(['user' => ['id' => 4711, ...$account]]),
    ]);

    expect(rideWithGpsSignIn($scriptedSignIn)->user()->getName())->toBe($name);
})->with([
    'display name' => [['name' => '', 'display_name' => 'roadrider'], 'roadrider'],
    'first and last name' => [['first_name' => 'Road', 'last_name' => 'Rider'], 'Road Rider'],
    'nothing' => [[], ''],
]);

it('fails when Ride with GPS sends no account', function (): void {
    rideWithGpsSignIn(new ScriptedSignIn([
        rideWithGpsSignInAnswer(['access_token' => 'access-abc']),
        rideWithGpsSignInAnswer(['error' => 'unauthorized']),
    ]))->user();
})->throws(UnexpectedValueException::class, 'Ride with GPS sent no account.');
