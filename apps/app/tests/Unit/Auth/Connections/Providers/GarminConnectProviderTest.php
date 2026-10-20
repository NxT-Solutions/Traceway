<?php

declare(strict_types=1);

use App\Auth\Connections\Providers\GarminConnectProvider;
use GuzzleHttp\Psr7\Response;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, string>  $urls
 */
function garminConnectSignIn(ScriptedSignIn $scriptedSignIn, array $urls = []): GarminConnectProvider
{
    config(['services.garmin_connect' => [
        'client_id' => 'garmin-client',
        'client_secret' => 'garmin-secret',
        'redirect' => 'https://traceway.test/auth/garmin_connect/callback',
        ...$urls,
    ]]);

    return $scriptedSignIn->provider('garmin_connect', GarminConnectProvider::class);
}

/**
 * @param  array<string, mixed>  $body
 */
function garminConnectSignInAnswer(array $body): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

it('sends the rider to Garmin with a PKCE challenge and no scopes', function (): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(garminConnectSignIn($scriptedSignIn));

    expect($authorize['url'])->toBe('https://connect.garmin.com/oauth2Confirm')
        ->and($authorize['query'])->toBe([
            'client_id' => 'garmin-client',
            'redirect_uri' => 'https://traceway.test/auth/garmin_connect/callback',
            'response_type' => 'code',
            'state' => $scriptedSignIn->request->session()->get('state'),
            'code_challenge' => $scriptedSignIn->challenge(),
            'code_challenge_method' => 'S256',
        ])
        ->and($scriptedSignIn->request->session()->get('code_verifier'))->not->toBe(ScriptedSignIn::VERIFIER);
});

it('trades the code and the verifier for keys, then reads the rider number with them', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        garminConnectSignInAnswer(['access_token' => 'access-abc', 'refresh_token' => 'refresh-abc', 'expires_in' => 86400]),
        garminConnectSignInAnswer(['userId' => 'b9d6c3e2-garmin']),
    ]);

    $user = garminConnectSignIn($scriptedSignIn, [
        'token_url' => 'https://garmin-auth.test/token',
        'api_url' => 'https://garmin-api.test/',
    ])->user();

    $request = $scriptedSignIn->guzzle->request(0);
    parse_str((string) $request->getBody(), $form);
    $account = $scriptedSignIn->guzzle->request(1);

    expect((string) $request->getUri())->toBe('https://garmin-auth.test/token')
        ->and($form)->toBe([
            'grant_type' => 'authorization_code',
            'client_id' => 'garmin-client',
            'client_secret' => 'garmin-secret',
            'code' => 'the-code',
            'redirect_uri' => 'https://traceway.test/auth/garmin_connect/callback',
            'code_verifier' => ScriptedSignIn::VERIFIER,
        ])
        ->and($scriptedSignIn->request->session()->has('code_verifier'))->toBeFalse()
        ->and((string) $account->getUri())->toBe('https://garmin-api.test/wellness-api/rest/user/id')
        ->and($account->getHeaderLine('Authorization'))->toBe('Bearer access-abc')
        ->and($user->getId())->toBe('b9d6c3e2-garmin')
        ->and($user->getName())->toBeNull()
        ->and($user->getEmail())->toBeNull()
        ->and($user->token)->toBe('access-abc')
        ->and($user->refreshToken)->toBe('refresh-abc')
        ->and($user->expiresIn)->toBe(86400);
});

it('fails when Garmin sends something that is not a rider number', function (): void {
    garminConnectSignIn(new ScriptedSignIn([
        garminConnectSignInAnswer(['access_token' => 'access-abc']),
        new Response(200, ['Content-Type' => 'text/plain'], 'Service Unavailable'),
    ]))->user();
})->throws(UnexpectedValueException::class, 'Garmin sent no user number.');
