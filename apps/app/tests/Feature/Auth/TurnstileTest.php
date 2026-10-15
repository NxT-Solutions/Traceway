<?php

declare(strict_types=1);

use App\Integrations\Cloudflare\Requests\SiteVerifyRequest;
use App\Integrations\Cloudflare\TurnstileConnector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
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
