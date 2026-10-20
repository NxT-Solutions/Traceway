<?php

declare(strict_types=1);

use App\Auth\Connections\LastSignIn;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as MatchedRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * A rider logging in on the given route, as the Login event sees the request.
 */
function signInMethodCookieLogsInOn(string $routeName, User $user): void
{
    $request = Request::create(route($routeName), 'POST');
    $request->setLaravelSession(resolve('session.store'));
    $request->setRouteResolver(fn (): MatchedRoute => Route::getRoutes()->match($request));

    app()->instance('request', $request);

    event(new Login('web', $user, remember: false));
}

/**
 * A rider with two-factor sign-in whose Google account is connected, and Google answering for it.
 */
function signInMethodCookieTwoFactorGoogleRider(): User
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => '1001', 'email' => 'ann@gmail.com', 'email_verified' => true])
        ->map(['id' => '1001', 'name' => 'Ann Rider', 'email' => 'ann@gmail.com']));
    $user = User::factory()->withTwoFactor()->create();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    return $user;
}

it('marks a passkey as last used when the rider logs in with one', function (): void {
    signInMethodCookieLogsInOn('passkey.login', User::factory()->create());

    expect(Cookie::queued(LastSignIn::COOKIE)?->getValue())->toBe(LastSignIn::PASSKEY);
});

it('marks the password as last used when a rider opens an account with one', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Ann Rider',
        'email' => 'ann@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCookie(LastSignIn::COOKIE, LastSignIn::PASSWORD);

    $this->assertAuthenticated();
});

it('keeps a rider with two-factor sign-in logged in on this browser after the code, when they asked to be remembered', function (): void {
    $user = signInMethodCookieTwoFactorGoogleRider();

    $this->get(route('connections.sign-in', ['provider' => 'google', 'remember' => 1]));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('two-factor.login'))
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

it('does not keep a rider with two-factor sign-in logged in who did not ask to be remembered', function (): void {
    $user = signInMethodCookieTwoFactorGoogleRider();

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']));

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('dashboard'))
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
});
