<?php

declare(strict_types=1);

use App\Auth\Connections\AppleCallbackTicket;
use App\Auth\Connections\LastSignIn;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * Apple signs a fresh secret with the team's key, so it needs these four to be set up.
 */
function appleSignInSetUp(): void
{
    config([
        'services.apple.client_id' => 'online.traceway.signin',
        'services.apple.team_id' => 'TEAM123456',
        'services.apple.key_id' => 'KEY1234567',
        'services.apple.private_key' => 'a-private-key',
    ]);
}

/**
 * The account Apple's provider builds from the signed token. Like Apple's own provider, it names no scopes.
 */
function appleSignInAccount(): SocialiteUser
{
    return (new SocialiteUser)
        ->setRaw(['sub' => '001234.apple', 'email' => 'ann@privaterelay.appleid.com', 'email_verified' => 'true'])
        ->map(['id' => '001234.apple', 'name' => null, 'email' => 'ann@privaterelay.appleid.com'])
        ->setToken('apple-id-token');
}

it('holds the answer Apple posts and sends the browser on with only a ticket in the address', function (): void {
    appleSignInSetUp();

    $response = $this->post(route('connections.apple'), [
        'code' => 'apple-code',
        'state' => 'apple-state',
        'user' => '{"name":{"firstName":"Ann","lastName":"Rider"}}',
    ]);

    $response->assertStatus(303);

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect(parse_url($location, PHP_URL_PATH))->toBe('/auth/apple/callback')
        ->and(array_keys($query))->toBe(['ticket'])
        ->and($location)->not->toContain('apple-code')
        ->and($location)->not->toContain('Rider')
        ->and($response->headers->getCookies())->toBe([]);
});

it('runs the Apple post outside the session and the form check, which Apple cannot pass', function (): void {
    // The HTTP kernel hands its middleware groups to the router when it is made.
    resolve(Kernel::class);
    $router = resolve(Router::class);

    $callback = $router->gatherRouteMiddleware(Route::getRoutes()->getByName('connections.callback'));
    $apple = $router->gatherRouteMiddleware(Route::getRoutes()->getByName('connections.apple'));

    expect($callback)->toContain(StartSession::class, PreventRequestForgery::class)
        ->and($apple)->not->toContain(StartSession::class)
        ->and($apple)->not->toContain(PreventRequestForgery::class);
});

it("logs in with the answer Apple's ticket held", function (): void {
    appleSignInSetUp();
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('apple')->create(['provider_user_id' => '001234.apple']);
    $seen = [];
    Socialite::fake('apple', function () use (&$seen): SocialiteUser {
        $seen = request()->only(['code', 'state', 'user', 'ticket']);

        return appleSignInAccount();
    });

    $this->get(route('connections.sign-in', ['provider' => 'apple']))->assertRedirect('https://socialite.fake/apple/authorize');
    $location = (string) $this->post(route('connections.apple'), ['code' => 'apple-code', 'state' => 'apple-state', 'user' => '{"email":"ann@privaterelay.appleid.com"}'])
        ->headers->get('Location');

    $this->get($location)
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, 'apple');

    $this->assertAuthenticatedAs($user);
    expect($seen)->toBe(['code' => 'apple-code', 'state' => 'apple-state', 'user' => '{"email":"ann@privaterelay.appleid.com"}']);
});

it('turns away an Apple ticket that was used already or never given', function (string $ticket): void {
    appleSignInSetUp();
    ConnectedAccount::factory()->signInOnly('apple')->create(['provider_user_id' => '001234.apple']);
    $asked = false;
    Socialite::fake('apple', function () use (&$asked): SocialiteUser {
        $asked = true;

        return appleSignInAccount();
    });

    $this->get(route('connections.sign-in', ['provider' => 'apple']));
    $this->get(route('connections.callback', ['provider' => 'apple', 'ticket' => $ticket]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That took too long, or it started in another tab. Try again.']);

    $this->assertGuest();
    expect($asked)->toBeFalse();
})->with([
    'a used ticket' => fn (): string => tap(
        resolve(AppleCallbackTicket::class)->hold(['code' => 'apple-code', 'state' => 'apple-state']),
        fn (string $ticket): ?array => resolve(AppleCallbackTicket::class)->redeem($ticket),
    ),
    'a ticket Traceway never gave' => fn (): string => Str::random(48),
]);

it('holds nothing Apple posts while Apple is not set up here', function (): void {
    $mock = Mockery::spy(resolve('cache.store'));
    app()->instance('cache.store', $mock);

    $this->post(route('connections.apple'), ['code' => 'apple-code', 'state' => 'apple-state'])->assertNotFound();

    $mock->shouldNotHaveReceived('put');
});

it('holds nothing when a field is longer than Apple sends, or not text, and the callback says to try again', function (array $answer): void {
    appleSignInSetUp();
    $asked = false;
    Socialite::fake('apple', function () use (&$asked): SocialiteUser {
        $asked = true;

        return appleSignInAccount();
    });
    $this->get(route('connections.sign-in', ['provider' => 'apple']));
    $mock = Mockery::spy(resolve('cache.store'));
    app()->instance('cache.store', $mock);

    $location = (string) $this->post(route('connections.apple'), $answer)
        ->assertStatus(303)
        ->headers->get('Location');

    expect($location)->toBe(route('connections.callback', ['provider' => 'apple']));
    $mock->shouldNotHaveReceived('put');

    $this->get($location)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That took too long, or it started in another tab. Try again.']);

    $this->assertGuest();
    expect($asked)->toBeFalse();
})->with([
    'a long code' => [['code' => str_repeat('a', 513), 'state' => 'apple-state']],
    'a long state' => [['code' => 'apple-code', 'state' => str_repeat('s', 129)]],
    'a long name' => [['code' => 'apple-code', 'state' => 'apple-state', 'user' => str_repeat('u', 2049)]],
    'a long error' => [['state' => 'apple-state', 'error' => str_repeat('e', 65)]],
    'a list' => [['code' => ['apple-code'], 'state' => 'apple-state']],
]);
