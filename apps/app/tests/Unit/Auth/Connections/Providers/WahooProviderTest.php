<?php

declare(strict_types=1);

use App\Auth\Connections\Providers\WahooProvider;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\Two\InvalidStateException;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

function wahooSignIn(ScriptedSignIn $scriptedSignIn, bool $requestEmail = false): WahooProvider
{
    config(['services.wahoo' => [
        'client_id' => 'wahoo-client',
        'client_secret' => 'wahoo-secret',
        'redirect' => 'https://traceway.test/settings/services/wahoo/callback',
        'url' => 'https://wahoo.test/',
        'request_email' => $requestEmail,
    ]]);

    return $scriptedSignIn->provider('wahoo', WahooProvider::class);
}

/**
 * @param  array<string, mixed>  $body
 */
function wahooSignInAnswer(array $body, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
}

it('asks Wahoo to let Traceway read who the rider is and write routes', function (): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(wahooSignIn($scriptedSignIn));

    expect($authorize['url'])->toBe('https://wahoo.test/oauth/authorize')
        ->and($authorize['query'])->toBe([
            'client_id' => 'wahoo-client',
            'redirect_uri' => 'https://traceway.test/settings/services/wahoo/callback',
            'scope' => 'user_read routes_read routes_write',
            'response_type' => 'code',
            'state' => $scriptedSignIn->request->session()->get('state'),
        ])
        ->and($authorize['query']['state'])->toHaveLength(40)
        ->and($scriptedSignIn->guzzle->requests())->toBe([]);
});

it('asks for the email only once the Wahoo app has that permission, or Wahoo would refuse the whole sign-in', function (): void {
    $scriptedSignIn = new ScriptedSignIn;

    $authorize = $scriptedSignIn->authorize(wahooSignIn($scriptedSignIn, requestEmail: true));

    expect($authorize['query']['scope'])->toBe('user_read routes_read routes_write email');
});

it('trades the code for keys as a form, then reads the rider with them', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        wahooSignInAnswer(['access_token' => 'access-abc', 'refresh_token' => 'refresh-abc', 'expires_in' => 7200, 'scope' => 'user_read email routes_read routes_write']),
        wahooSignInAnswer(['id' => 60462, 'first' => 'Road', 'last' => 'Rider', 'email' => 'road@wahoo.test']),
    ]);

    $user = wahooSignIn($scriptedSignIn)->user();

    $request = $scriptedSignIn->guzzle->request(0);
    parse_str((string) $request->getBody(), $form);
    $account = $scriptedSignIn->guzzle->request(1);

    expect((string) $request->getUri())->toBe('https://wahoo.test/oauth/token')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($form)->toBe([
            'grant_type' => 'authorization_code',
            'client_id' => 'wahoo-client',
            'client_secret' => 'wahoo-secret',
            'code' => 'the-code',
            'redirect_uri' => 'https://traceway.test/settings/services/wahoo/callback',
        ])
        ->and((string) $account->getUri())->toBe('https://wahoo.test/v1/user')
        ->and($account->getHeaderLine('Authorization'))->toBe('Bearer access-abc')
        ->and($user->getId())->toBe('60462')
        ->and($user->getName())->toBe('Road Rider')
        ->and($user->getEmail())->toBe('road@wahoo.test')
        ->and($user->getNickname())->toBeNull()
        ->and($user->token)->toBe('access-abc')
        ->and($user->refreshToken)->toBe('refresh-abc')
        ->and($user->expiresIn)->toBe(7200)
        ->and($user->approvedScopes)->toBe(['user_read', 'email', 'routes_read', 'routes_write']);
});

it('keeps a rider without a name or an email, who still has a number', function (): void {
    $scriptedSignIn = new ScriptedSignIn([
        wahooSignInAnswer(['access_token' => 'access-abc']),
        wahooSignInAnswer(['id' => '60462', 'first' => null]),
    ]);

    $user = wahooSignIn($scriptedSignIn)->user();

    expect($user->getId())->toBe('60462')
        ->and($user->getName())->toBe('')
        ->and($user->getEmail())->toBeNull();
});

it('sends nothing to Wahoo for an answer that did not start here', function (): void {
    $scriptedSignIn = new ScriptedSignIn(query: ['code' => 'the-code', 'state' => 'forged']);

    expect(fn (): mixed => wahooSignIn($scriptedSignIn)->user())->toThrow(InvalidStateException::class)
        ->and($scriptedSignIn->guzzle->requests())->toBe([]);
});

it('fails when Wahoo turns the code down', function (): void {
    wahooSignIn(new ScriptedSignIn([
        wahooSignInAnswer(['error' => 'invalid_grant'], 400),
    ]))->user();
})->throws(ClientException::class);

it('fails when Wahoo sends something that is not an account', function (): void {
    wahooSignIn(new ScriptedSignIn([
        wahooSignInAnswer(['access_token' => 'access-abc']),
        new Response(200, ['Content-Type' => 'text/html'], '<html>Down for maintenance</html>'),
    ]))->user();
})->throws(UnexpectedValueException::class, 'Wahoo sent no account.');
