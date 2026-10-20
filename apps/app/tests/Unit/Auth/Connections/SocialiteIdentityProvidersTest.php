<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Auth\Connections\SocialiteIdentityProviders;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\ScriptedSignIn;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The sign-in services as a callback sees them, for a rider who came back with the given query. Every service
 * starts switched off, whatever the local .env holds.
 *
 * @param  array<string, string>  $query
 */
function identityProvidersFor(array $query = ['code' => 'the-code']): SocialiteIdentityProviders
{
    $request = Request::create('/auth/callback', 'GET', $query);
    $request->setLaravelSession(new Store('traceway_session', new ArraySessionHandler(120)));

    app()->instance('request', $request);

    return new SocialiteIdentityProviders(resolve(Factory::class), resolve(Repository::class), $request);
}

function identityProvidersSwitchedOff(): void
{
    foreach (ConnectionProvider::slugs() as $slug) {
        config(['services.'.$slug.'.client_id' => null]);
    }
}

/**
 * What a service says about the rider, as Socialite hands it over.
 *
 * @param  array<string, mixed>  $fields
 * @param  array<string, mixed>  $raw
 */
function identityProvidersUser(array $fields = [], array $raw = []): SocialiteUser
{
    return (new SocialiteUser)->setRaw($raw)->map([
        'id' => '4711',
        'nickname' => null,
        'name' => 'Road Rider',
        'email' => 'road@traceway.test',
        ...$fields,
    ]);
}

/**
 * Asserts the callback stops with the given problem.
 */
function identityProvidersFailsWith(ConnectionProblem $connectionProblem, Closure $closure): void
{
    expect($closure)->toThrow(function (ConnectionFailed $connectionFailed) use ($connectionProblem): void {
        expect($connectionFailed->problem)->toBe($connectionProblem);
    });
}

it('turns a service on only when it has its keys', function (string $slug, ?array $keys, bool $enabled): void {
    identityProvidersSwitchedOff();
    config(['services.'.$slug => $keys]);

    expect(identityProvidersFor()->isEnabled(ConnectionProvider::from($slug)))->toBe($enabled);
})->with([
    'client id and secret' => ['google', ['client_id' => 'id', 'client_secret' => 'secret'], true],
    'no secret' => ['google', ['client_id' => 'id', 'client_secret' => null], false],
    'blank id' => ['wahoo', ['client_id' => '  ', 'client_secret' => 'secret'], false],
    'blank secret' => ['wahoo', ['client_id' => 'id', 'client_secret' => ''], false],
    'id that is not text' => ['github', ['client_id' => 12345, 'client_secret' => 'secret'], false],
    'no settings at all' => ['discord', null, false],
    'Apple with its team, key, and private key' => ['apple', ['client_id' => 'online.traceway', 'client_secret' => null, 'team_id' => 'TEAM', 'key_id' => 'KEY', 'private_key' => 'PRIVATE'], true],
    'Apple without a key id' => ['apple', ['client_id' => 'online.traceway', 'team_id' => 'TEAM', 'key_id' => null, 'private_key' => 'PRIVATE'], false],
    'Apple with a blank private key' => ['apple', ['client_id' => 'online.traceway', 'team_id' => 'TEAM', 'key_id' => 'KEY', 'private_key' => ' '], false],
    'Apple with only a client id and secret' => ['apple', ['client_id' => 'online.traceway', 'client_secret' => 'secret'], false],
]);

it('lists the services that are on in the order the sign-in page offers them', function (): void {
    identityProvidersSwitchedOff();

    expect(identityProvidersFor()->enabled())->toBe([]);

    foreach (['openstreetmap', 'wahoo', 'google', 'strava'] as $slug) {
        config(['services.'.$slug.'.client_id' => $slug.'-id', 'services.'.$slug.'.client_secret' => $slug.'-secret']);
    }

    expect(identityProvidersFor()->enabled())->toBe([
        ConnectionProvider::Google,
        ConnectionProvider::Strava,
        ConnectionProvider::Wahoo,
        ConnectionProvider::OpenStreetMap,
    ]);
});

it('sends the rider to the service, with PKCE where the service supports it', function (string $slug, bool $pkce): void {
    config(['services.'.$slug => ['client_id' => $slug.'-id', 'client_secret' => $slug.'-secret', 'redirect' => 'https://traceway.test/auth/'.$slug.'/callback']]);

    $url = identityProvidersFor()->redirectUrl(ConnectionProvider::from($slug));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['client_id'] ?? null)->toBe($slug.'-id')
        ->and($query['redirect_uri'] ?? null)->toBe('https://traceway.test/auth/'.$slug.'/callback')
        ->and(isset($query['code_challenge']))->toBe($pkce)
        ->and($query['code_challenge_method'] ?? null)->toBe($pkce ? 'S256' : null);
})->with([
    'Google' => ['google', true],
    'GitHub' => ['github', true],
    'Discord' => ['discord', true],
    'OpenStreetMap' => ['openstreetmap', true],
    'Facebook' => ['facebook', false],
    'Wahoo' => ['wahoo', false],
]);

it('asks Google which account to use, for a shared computer', function (): void {
    config(['services.google' => ['client_id' => 'google-id', 'client_secret' => 'google-secret', 'redirect' => 'https://traceway.test/auth/google/callback']]);

    parse_str((string) parse_url(identityProvidersFor()->redirectUrl(ConnectionProvider::Google), PHP_URL_QUERY), $query);

    expect($query['prompt'] ?? null)->toBe('select_account')
        ->and($query['scope'] ?? null)->toBe('openid profile email');
});

it('says the service could not be reached when its sign-in cannot be built', function (): void {
    config(['services.google' => ['client_id' => 'google-id', 'client_secret' => 'google-secret']]);

    identityProvidersFailsWith(ConnectionProblem::Unavailable, fn (): string => identityProvidersFor()->redirectUrl(ConnectionProvider::Google));
});

it('says the rider cancelled when they said no at the service', function (string $error): void {
    Socialite::fake('google', identityProvidersUser());

    identityProvidersFailsWith(ConnectionProblem::Cancelled, fn (): ConnectedIdentity => identityProvidersFor(['error' => $error, 'state' => 'x'])->identity(ConnectionProvider::Google));
})->with(['access_denied', 'user_cancelled_authorize', 'user_denied', 'consent_required', 'login_required']);

it('says the service turned the request down for any other error', function (): void {
    Socialite::fake('google', identityProvidersUser());

    identityProvidersFailsWith(ConnectionProblem::Refused, fn (): ConnectedIdentity => identityProvidersFor(['error' => 'invalid_scope'])->identity(ConnectionProvider::Google));
});

it('treats an answer without a code as cancelled', function (array $query): void {
    Socialite::fake('google', identityProvidersUser());

    identityProvidersFailsWith(ConnectionProblem::Cancelled, fn (): ConnectedIdentity => identityProvidersFor($query)->identity(ConnectionProvider::Google));
})->with([
    'no code' => [['state' => 'x']],
    'empty code' => [['code' => '']],
    'empty error' => [['error' => '']],
]);

it('names what went wrong when the service answers', function (Throwable $throwable, ConnectionProblem $connectionProblem): void {
    Socialite::fake('wahoo', fn (): never => throw $throwable);

    identityProvidersFailsWith($connectionProblem, fn (): ConnectedIdentity => identityProvidersFor()->identity(ConnectionProvider::Wahoo));
})->with([
    'a state that does not match' => [new InvalidStateException, ConnectionProblem::Expired],
    'a code the service turned down' => [new ClientException('Bad code.', new GuzzleRequest('POST', 'https://wahoo.test/oauth/token'), new GuzzleResponse(400)), ConnectionProblem::Refused],
    'a service that cannot be reached' => [new ConnectException('Timed out.', new GuzzleRequest('POST', 'https://wahoo.test/oauth/token')), ConnectionProblem::Unavailable],
    'an answer that is not an account' => [new UnexpectedValueException('Wahoo sent no account.'), ConnectionProblem::Incomplete],
]);

it('refuses an account without a number at the service', function (mixed $id): void {
    Socialite::fake('google', identityProvidersUser(['id' => $id]));

    identityProvidersFailsWith(ConnectionProblem::Incomplete, fn (): ConnectedIdentity => identityProvidersFor()->identity(ConnectionProvider::Google));
})->with([
    'none' => [null],
    'empty' => [''],
    'blank' => ['   '],
    'not text' => [['4711']],
]);

it('refuses a number that cannot be one', function (): void {
    Socialite::fake('google', identityProvidersUser(['id' => "47\n11"]));

    identityProvidersFailsWith(ConnectionProblem::Incomplete, fn (): ConnectedIdentity => identityProvidersFor()->identity(ConnectionProvider::Google));
});

it('reads who the rider is at the service', function (): void {
    Socialite::fake('google', identityProvidersUser(['id' => 4711, 'name' => '  Road Rider ', 'email' => 'Road@Gmail.test'], ['email_verified' => true]));

    $connectedIdentity = identityProvidersFor()->identity(ConnectionProvider::Google);

    expect($connectedIdentity->provider)->toBe(ConnectionProvider::Google)
        ->and($connectedIdentity->providerUserId)->toBe('4711')
        ->and($connectedIdentity->name?->value)->toBe('Road Rider')
        ->and($connectedIdentity->email?->value)->toBe('road@gmail.test')
        ->and($connectedIdentity->emailVerified)->toBeTrue();
});

it('reads a rider built without the service answer', function (): void {
    Socialite::fake('google', (new SocialiteUser)->map(['id' => '4711', 'email' => 'road@gmail.test']));

    $connectedIdentity = identityProvidersFor()->identity(ConnectionProvider::Google);

    expect($connectedIdentity->providerUserId)->toBe('4711')
        ->and($connectedIdentity->emailVerified)->toBeFalse();
});

it('reads an Apple rider, whose sign-in names no scopes', function (): void {
    Socialite::fake('apple', (new SocialiteUser)
        ->setRaw(['sub' => '001234.abcdef', 'email' => 'x7k2@privaterelay.appleid.com', 'email_verified' => 'true'])
        ->map(['id' => '001234.abcdef', 'name' => null, 'email' => 'x7k2@privaterelay.appleid.com'])
        ->setToken('apple-id-token'));

    $connectedIdentity = identityProvidersFor()->identity(ConnectionProvider::Apple);

    expect($connectedIdentity->providerUserId)->toBe('001234.abcdef')
        ->and($connectedIdentity->email?->value)->toBe('x7k2@privaterelay.appleid.com')
        ->and($connectedIdentity->emailVerified)->toBeTrue()
        ->and($connectedIdentity->accountLabel)->toBe('x7k2@privaterelay.appleid.com')
        ->and($connectedIdentity->scopes)->toBe([])
        ->and($connectedIdentity->tokens)->toBeNull();
});

it('drops a name or an email that is not one, rather than refusing the rider', function (): void {
    Socialite::fake('github', identityProvidersUser(['name' => "\u{0007}", 'email' => 'not an email']));

    $connectedIdentity = identityProvidersFor()->identity(ConnectionProvider::GitHub);

    expect($connectedIdentity->name)->toBeNull()
        ->and($connectedIdentity->email)->toBeNull()
        ->and($connectedIdentity->emailVerified)->toBeFalse();
});

it('trusts an email as confirmed only when the service says it confirmed it', function (string $slug, array $raw, bool $confirmed): void {
    Socialite::fake($slug, identityProvidersUser([], $raw));

    expect(identityProvidersFor()->identity(ConnectionProvider::from($slug))->emailVerified)->toBe($confirmed);
})->with([
    'Google, confirmed' => ['google', ['email_verified' => true], true],
    'Google, not confirmed' => ['google', ['email_verified' => false], false],
    'Google, as the word' => ['google', ['email_verified' => 'true'], false],
    'Google, silent' => ['google', [], false],
    'Apple, as the word' => ['apple', ['email_verified' => 'true'], true],
    'Apple, confirmed' => ['apple', ['email_verified' => true], true],
    'Apple, not confirmed' => ['apple', ['email_verified' => 'false'], false],
    'Discord, verified' => ['discord', ['verified' => true], true],
    'Discord, not verified' => ['discord', ['verified' => false], false],
    'GitHub' => ['github', [], true],
    'X' => ['x', [], true],
    'Facebook' => ['facebook', ['email_verified' => true], false],
    'Wahoo' => ['wahoo', ['email_verified' => true], false],
    'Strava, with a verified flag' => ['strava', ['verified' => true], false],
]);

it('never trusts an email the service did not send', function (): void {
    Socialite::fake('github', identityProvidersUser(['email' => null]));

    $connectedIdentity = identityProvidersFor()->identity(ConnectionProvider::GitHub);

    expect($connectedIdentity->email)->toBeNull()->and($connectedIdentity->emailVerified)->toBeFalse();
});

it('keeps the keys only for services that need them, with the time they run out', function (string $slug, bool $kept): void {
    Date::setTestNow('2026-10-20 08:00:00');
    Socialite::fake($slug, identityProvidersUser()->setToken('access-abc')->setRefreshToken('refresh-abc')->setExpiresIn(7200));

    $connectionTokens = identityProvidersFor()->identity(ConnectionProvider::from($slug))->tokens;

    if (! $kept) {
        expect($connectionTokens)->toBeNull();

        return;
    }

    expect($connectionTokens?->accessToken)->toBe('access-abc')
        ->and($connectionTokens?->refreshToken)->toBe('refresh-abc')
        ->and($connectionTokens?->expiresAt)->toBe(Date::now()->getTimestamp() + 7200);
})->with([
    'Wahoo' => ['wahoo', true],
    'Ride with GPS' => ['ride_with_gps', true],
    'Garmin Connect' => ['garmin_connect', true],
    'Google' => ['google', false],
    'Strava' => ['strava', false],
    'GitHub' => ['github', false],
]);

it('keeps a key that does not run out, or comes without a refresh token', function (mixed $expiresIn, ?int $expiresAfter): void {
    Date::setTestNow('2026-10-20 08:00:00');
    Socialite::fake('ride_with_gps', identityProvidersUser()->setToken('access-abc')->setRefreshToken('')->setExpiresIn($expiresIn));

    $connectionTokens = identityProvidersFor()->identity(ConnectionProvider::RideWithGps)->tokens;

    expect($connectionTokens?->accessToken)->toBe('access-abc')
        ->and($connectionTokens?->refreshToken)->toBeNull()
        ->and($connectionTokens?->expiresAt)->toBe($expiresAfter === null ? null : Date::now()->getTimestamp() + $expiresAfter);
})->with([
    'no expiry' => [null, null],
    'zero' => [0, null],
    'negative' => [-60, null],
    'as text' => ['3600', 3600],
]);

it('keeps no keys when the service gave no access token', function (): void {
    Socialite::fake('wahoo', identityProvidersUser()->setToken(''));

    expect(identityProvidersFor()->identity(ConnectionProvider::Wahoo)->tokens)->toBeNull();
});

it('labels the account the way each service knows the rider', function (string $slug, ?string $nickname, ?string $email, ?string $label): void {
    Socialite::fake($slug, identityProvidersUser(['nickname' => $nickname, 'email' => $email]));

    expect(identityProvidersFor()->identity(ConnectionProvider::from($slug))->accountLabel)->toBe($label);
})->with([
    'X by username' => ['x', 'roadrider', 'road@x.test', 'roadrider'],
    'GitHub by username' => ['github', 'roadrider', 'road@github.test', 'roadrider'],
    'Discord by username' => ['discord', 'roadrider', 'road@discord.test', 'roadrider'],
    'OpenStreetMap by display name' => ['openstreetmap', 'Road Rider', null, 'Road Rider'],
    'GitHub by email without a username' => ['github', null, 'road@github.test', 'road@github.test'],
    'Google by email' => ['google', 'roadrider', 'road@gmail.test', 'road@gmail.test'],
    'Wahoo by email' => ['wahoo', null, 'road@wahoo.test', 'road@wahoo.test'],
    'Facebook by name without an email' => ['facebook', 'Road Rider', null, 'Road Rider'],
    'Strava not at all' => ['strava', 'roadrider', 'road@strava.test', null],
    'Garmin Connect not at all' => ['garmin_connect', 'roadrider', null, null],
    'Runkeeper not at all' => ['runkeeper', 'roadrider', 'road@runkeeper.test', null],
]);

it('labels an Instagram account by its username, which the provider hands over as the name', function (): void {
    Socialite::fake('instagram', identityProvidersUser(['nickname' => null, 'name' => 'roadrider', 'email' => null]));

    expect(identityProvidersFor()->identity(ConnectionProvider::Instagram)->accountLabel)->toBe('roadrider');
});

it('asks Facebook only for the name and email', function (): void {
    config(['services.facebook' => ['client_id' => 'facebook-id', 'client_secret' => 'facebook-secret', 'redirect' => 'https://traceway.test/auth/facebook/callback']]);
    $scriptedSignIn = new ScriptedSignIn([
        new GuzzleResponse(200, [], '{"access_token":"facebook-token","token_type":"bearer","expires_in":5183944}'),
        new GuzzleResponse(200, [], '{"id":"10","name":"Road Rider","email":"road@facebook.test"}'),
    ]);
    $scriptedSignIn->provider('facebook', FacebookProvider::class);

    $connectedIdentity = identityProvidersFor(['code' => 'the-code', 'state' => ScriptedSignIn::STATE])->identity(ConnectionProvider::Facebook);
    parse_str($scriptedSignIn->guzzle->request(1)->getUri()->getQuery(), $query);

    expect($query['fields'] ?? null)->toBe('name,email')
        ->and($connectedIdentity->providerUserId)->toBe('10')
        ->and($connectedIdentity->email?->value)->toBe('road@facebook.test');
});

it('keeps the access token out of what it reports when the service turns the rider down', function (): void {
    Exceptions::fake();
    config(['services.facebook' => ['client_id' => 'facebook-id', 'client_secret' => 'facebook-secret', 'redirect' => 'https://traceway.test/auth/facebook/callback']]);
    $scriptedSignIn = new ScriptedSignIn([
        new GuzzleResponse(200, [], '{"access_token":"facebook-token","token_type":"bearer","expires_in":5183944}'),
        new GuzzleResponse(400, [], '{"error":{"message":"Too many calls for facebook-token"}}'),
    ]);
    $scriptedSignIn->provider('facebook', FacebookProvider::class);

    $caught = null;

    try {
        identityProvidersFor(['code' => 'the-code', 'state' => ScriptedSignIn::STATE])->identity(ConnectionProvider::Facebook);
    } catch (ConnectionFailed $connectionFailed) {
        $caught = $connectionFailed;
    }

    // Everything that could reach the log or Sentry: the reported exception and the chain under the failure.
    $chain = [];

    for ($throwable = $caught; $throwable instanceof Throwable; $throwable = $throwable->getPrevious()) {
        $chain[] = $throwable->getMessage();
    }

    expect($caught?->problem)->toBe(ConnectionProblem::Refused)
        ->and(implode(' ', $chain))->toContain('Facebook answered 400 to GET https://graph.facebook.com/')
        ->not->toContain('facebook-token')
        ->not->toContain('appsecret_proof');
    Exceptions::assertReported(fn (RuntimeException $runtimeException): bool => str_starts_with($runtimeException->getMessage(), 'Facebook answered 400')
        && ! str_contains($runtimeException->getMessage(), 'facebook-token'));
});

it('keeps what the rider allowed once each, as the service names it', function (): void {
    Socialite::fake('wahoo', identityProvidersUser()->setToken('access-abc')->setApprovedScopes(['user_read', ' routes_write ', '', 'user_read']));

    expect(identityProvidersFor()->identity(ConnectionProvider::Wahoo)->scopes)->toBe(['user_read', 'routes_write']);
});
