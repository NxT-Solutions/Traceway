<?php

declare(strict_types=1);

use App\Auth\Connections\Providers\StravaProvider;
use GuzzleHttp\Psr7\Response;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

function stravaSignIn(ScriptedSignIn $scriptedSignIn): StravaProvider
{
    config(['services.strava' => [
        'client_id' => 'strava-client',
        'client_secret' => 'strava-secret',
        'redirect' => 'https://traceway.test/auth/strava/callback',
    ]]);

    return $scriptedSignIn->provider('strava', StravaProvider::class);
}

/**
 * @param  array<string, mixed>  $body
 */
function stravaSignInAnswer(array $body): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

it('asks Strava only to read the public profile', function (): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(stravaSignIn($scriptedSignIn));

    expect($authorize['url'])->toBe('https://www.strava.com/oauth/authorize')
        ->and($authorize['query'])->toBe([
            'client_id' => 'strava-client',
            'redirect_uri' => 'https://traceway.test/auth/strava/callback',
            'scope' => 'read',
            'response_type' => 'code',
            'state' => $scriptedSignIn->request->session()->get('state'),
        ]);
});

it('reads the athlete number and name with the key Strava gave', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        stravaSignInAnswer(['access_token' => 'access-abc', 'refresh_token' => 'refresh-abc', 'expires_in' => 21600]),
        stravaSignInAnswer(['id' => 1_234_567, 'firstname' => 'Road', 'lastname' => 'Rider']),
    ]);

    $user = stravaSignIn($scriptedSignIn)->user();

    $request = $scriptedSignIn->guzzle->request(0);
    parse_str((string) $request->getBody(), $form);
    $account = $scriptedSignIn->guzzle->request(1);

    expect((string) $request->getUri())->toBe('https://www.strava.com/oauth/token')
        ->and($form['grant_type'] ?? null)->toBe('authorization_code')
        ->and($form['code'] ?? null)->toBe('the-code')
        ->and((string) $account->getUri())->toBe('https://www.strava.com/api/v3/athlete')
        ->and($account->getHeaderLine('Authorization'))->toBe('Bearer access-abc')
        ->and($user->getId())->toBe('1234567')
        ->and($user->getName())->toBe('Road Rider')
        ->and($user->getEmail())->toBeNull()
        ->and($user->getAvatar())->toBeNull();
});

it('keeps an athlete who shares no name', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        stravaSignInAnswer(['access_token' => 'access-abc']),
        stravaSignInAnswer(['id' => 1_234_567, 'firstname' => null]),
    ]);

    $user = stravaSignIn($scriptedSignIn)->user();

    expect($user->getId())->toBe('1234567')->and($user->getName())->toBe('');
});
