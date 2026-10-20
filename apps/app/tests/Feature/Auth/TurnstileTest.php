<?php

declare(strict_types=1);

use App\Integrations\Cloudflare\Requests\SiteVerifyRequest;
use App\Integrations\Cloudflare\TurnstileConnector;
use App\Mail\AccountMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

/**
 * With both keys set, sign-up and the reset link ask Cloudflare whether a
 * person filled the form.
 */
function turnstileAnswers(MockResponse $mockResponse): MockClient
{
    config([
        'services.turnstile.site_key' => 'site-key',
        'services.turnstile.secret_key' => 'secret-key',
    ]);

    $mock = new MockClient([SiteVerifyRequest::class => $mockResponse]);
    $connector = new TurnstileConnector;
    $connector->withMockClient($mock);

    app()->instance(TurnstileConnector::class, $connector);

    return $mock;
}

/**
 * @return array<string, string>
 */
function signUp(string $token = 'a-token'): array
{
    return [
        'name' => 'Ann Rider',
        'email' => 'ann@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf-turnstile-response' => $token,
    ];
}

it('creates the account when Cloudflare says a person filled the form', function (): void {
    $mockClient = turnstileAnswers(MockResponse::make(['success' => true]));

    $this->post(route('register.store'), signUp())->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect($mockClient->getLastPendingRequest()?->body()?->all())->toBe([
        'secret' => 'secret-key',
        'response' => 'a-token',
        'remoteip' => '127.0.0.1',
    ]);
});

it('turns the form away when Cloudflare says no', function (): void {
    turnstileAnswers(MockResponse::make(['success' => false, 'error-codes' => ['invalid-input-response']]));

    $this->from(route('register'))->post(route('register.store'), signUp())
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('turnstile');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('turns the form away when the check did not run', function (): void {
    turnstileAnswers(MockResponse::make(['success' => false, 'error-codes' => ['missing-input-response']]));

    $this->from(route('register'))->post(route('register.store'), signUp(''))->assertSessionHasErrors('turnstile');

    $this->assertGuest();
});

it('lets a person through when Cloudflare does not answer', function (): void {
    Exceptions::fake();
    turnstileAnswers(MockResponse::make('', 503));

    $this->post(route('register.store'), signUp())->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    Exceptions::assertReported(RuntimeException::class);
});

it('asks before sending a reset link', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    turnstileAnswers(MockResponse::make(['success' => false]));

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email, 'cf-turnstile-response' => 'a-token'])
        ->assertSessionHasErrors('turnstile');

    Notification::assertNothingSent();
});

it('signs up without the check when no keys are set', function (): void {
    $this->get(route('register'))->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->component('auth/register')
        ->where('turnstileSiteKey', expected: null));

    $this->post(route('register.store'), signUp(''))->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('gives both forms the public key when the check is on', function (): void {
    turnstileAnswers(MockResponse::make(['success' => true]));

    $this->get(route('register'))->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->where('turnstileSiteKey', 'site-key'));
    $this->get(route('password.request'))->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->where('turnstileSiteKey', 'site-key'));
});

/**
 * A new rider comes back from OpenStreetMap, which shares no email, and lands on the last step of signing up.
 */
function turnstileFinishSignUpArrive(): void
{
    config(['services.openstreetmap.client_id' => 'osm-id', 'services.openstreetmap.client_secret' => 'osm-secret']);
    Socialite::fake('openstreetmap', (new SocialiteUser)
        ->map(['id' => '4711', 'name' => 'Ann Rider', 'email' => null])
        ->setApprovedScopes(['read_prefs']));

    test()->get(route('connections.sign-in', ['provider' => 'openstreetmap']));
    test()->get(route('connections.callback', ['provider' => 'openstreetmap', 'code' => 'from-openstreetmap', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));
}

it('asks before emailing the link that finishes a sign-up from another service', function (): void {
    Mail::fake();
    turnstileFinishSignUpArrive();
    turnstileAnswers(MockResponse::make(['success' => false]));

    $this->from(route('connections.finish'))
        ->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com', 'cf-turnstile-response' => 'a-token'])
        ->assertRedirect(route('connections.finish'))
        ->assertSessionHasErrors('turnstile');

    Mail::assertNothingQueued();
    expect(session('connections.pending'))->toBeString();
});

it('emails the link when Cloudflare says a person finished the sign-up', function (): void {
    Mail::fake();
    turnstileFinishSignUpArrive();
    $mockClient = turnstileAnswers(MockResponse::make(['success' => true]));

    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com', 'cf-turnstile-response' => 'a-token'])
        ->assertRedirect(route('login'))
        ->assertSessionDoesntHaveErrors();

    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-sign-up');
    expect($mockClient->getLastPendingRequest()?->body()?->all()['response'] ?? null)->toBe('a-token');
});
