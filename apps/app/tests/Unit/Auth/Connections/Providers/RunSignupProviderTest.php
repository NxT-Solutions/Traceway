<?php

declare(strict_types=1);

use App\Auth\Connections\Providers\RunSignupProvider;
use GuzzleHttp\Psr7\Response;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

function runSignupSignIn(ScriptedSignIn $scriptedSignIn, string $environment = 'prod'): RunSignupProvider
{
    config(['services.runsignup' => [
        'client_id' => 'runsignup-client',
        'client_secret' => 'runsignup-secret',
        'redirect' => 'https://traceway.test/auth/runsignup/callback',
        'environment' => $environment,
    ]]);

    return $scriptedSignIn->provider('runsignup', RunSignupProvider::class);
}

/**
 * @param  array<string, mixed>  $body
 */
function runSignupSignInAnswer(array $body): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

it('asks RunSignup only to read, with a PKCE challenge', function (string $environment, string $host): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(runSignupSignIn($scriptedSignIn, $environment));

    expect($authorize['url'])->toBe('https://'.$host.'/Profile/OAuth2/RequestGrant')
        ->and($authorize['query'])->toBe([
            'client_id' => 'runsignup-client',
            'redirect_uri' => 'https://traceway.test/auth/runsignup/callback',
            'scope' => 'rsu_api_read',
            'response_type' => 'code',
            'state' => $scriptedSignIn->request->session()->get('state'),
            'code_challenge' => $scriptedSignIn->challenge(),
            'code_challenge_method' => 'S256',
        ]);
})->with([
    'live site' => ['prod', 'runsignup.com'],
    'test site' => ['test', 'test.runsignup.com'],
]);

it('trades the code and the verifier for a key, then reads the rider with it', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        runSignupSignInAnswer(['access_token' => 'access-abc', 'refresh_token' => 'refresh-abc', 'expires_in' => 3600]),
        runSignupSignInAnswer(['user' => ['user_id' => 90210, 'first_name' => 'Road', 'last_name' => 'Rider', 'email' => 'road@runsignup.test']]),
    ]);

    $user = runSignupSignIn($scriptedSignIn)->user();

    $request = $scriptedSignIn->guzzle->request(0);
    parse_str((string) $request->getBody(), $form);
    $account = $scriptedSignIn->guzzle->request(1);

    expect((string) $request->getUri())->toBe('https://runsignup.com/Rest/v2/auth/auth-code-redemption.json')
        ->and($form['code'] ?? null)->toBe('the-code')
        ->and($form['code_verifier'] ?? null)->toBe(ScriptedSignIn::VERIFIER)
        ->and((string) $account->getUri())->toBe('https://runsignup.com/Rest/User/Self?format=json')
        ->and($account->getHeaderLine('Authorization'))->toBe('Bearer access-abc')
        ->and($user->getId())->toBe('90210')
        ->and($user->getName())->toBe('Road Rider')
        ->and($user->getEmail())->toBe('road@runsignup.test')
        ->and($user->getRaw())->toBe(['user_id' => 90210, 'first_name' => 'Road', 'last_name' => 'Rider', 'email' => 'road@runsignup.test']);
});

it('fails when RunSignup answers an error as if it were an account', function (array $body): void {
    runSignupSignIn(new ScriptedSignIn([
        runSignupSignInAnswer(['access_token' => 'access-abc']),
        runSignupSignInAnswer($body),
    ]))->user();
})->with([
    'error' => [['error' => ['error_code' => 6, 'error_msg' => 'Invalid access token']]],
    'error beside an account' => [['error' => 'expired', 'user' => ['user_id' => 90210]]],
    'no account' => [['users' => []]],
])->throws(UnexpectedValueException::class, 'RunSignup sent no account.');
