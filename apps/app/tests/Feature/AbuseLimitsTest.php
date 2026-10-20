<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * Each of these costs the map servers, the database, or an email, so one
 * account or one address gets only so many a minute. The limit sits in front
 * of validation, so empty requests count too. A test that reads the wait in
 * seconds freezes the clock, or a second can pass between the requests.
 */
it('lets a rider draw thirty routes a minute, then says when to draw again', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 30) as $ignored) {
        $this->actingAs($user)->postJson(route('routes.preview'), [])->assertUnprocessable();
    }

    $this->actingAs($user)->postJson(route('routes.build'), [])
        ->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'You have drawn a lot of routes this minute. You can draw again in '));
    $this->actingAs($user)->postJson(route('routes.multisport'), [])->assertTooManyRequests();
});

it('gives each rider their own drawing limit', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 30) as $ignored) {
        $this->actingAs($user)->postJson(route('routes.preview'), []);
    }

    $this->actingAs(User::factory()->create())->postJson(route('routes.preview'), [])->assertUnprocessable();
});

it('lets a rider save thirty times a minute', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 30) as $ignored) {
        $this->actingAs($user)->postJson(route('routes.saved.store'), [])->assertUnprocessable();
    }

    $this->actingAs($user)->postJson(route('routes.saved.store'), [])->assertTooManyRequests();
});

it('lets one address open shared links sixty times a minute', function (): void {
    $url = route('routes.share.gpx', ['savedRoute' => (string) Str::uuid()]);

    foreach (range(1, 60) as $ignored) {
        expect($this->get($url)->status())->not->toBe(429);
    }

    $this->get($url)->assertTooManyRequests();
});

it('builds a copy of your data three times a minute', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)->get(route('privacy.export'))->assertOk();
    }

    $this->actingAs($user)->get(route('privacy.export'))->assertTooManyRequests();
});

it('lets an AI tool register thirty times a minute from one address, but not the sign-in metadata', function (): void {
    foreach (range(1, 30) as $ignored) {
        expect($this->postJson('/oauth/register', [])->status())->not->toBe(429);
    }

    $this->postJson('/oauth/register', [])->assertTooManyRequests();

    foreach (range(1, 40) as $ignored) {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
    }
});

it('takes five sign-ups a minute from one address, then says when to try again', function (): void {
    $this->freezeTime();

    foreach (range(1, 5) as $ignored) {
        $this->from(route('register'))->post(route('register.store'), [])->assertSessionHasErrors('name');
    }

    $this->from(route('register'))->post(route('register.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email' => 'There were too many tries from here. Please wait 60 seconds, then try again.'])
        ->assertSessionHasInput('email', 'ann@example.com');
});

it('takes five reset-link requests a minute from one address', function (): void {
    $this->freezeTime();

    foreach (range(1, 5) as $ignored) {
        $this->from(route('password.request'))->post(route('password.email'), [])->assertSessionHasErrors('email');
    }

    $this->from(route('password.request'))->post(route('password.email'), ['email' => 'ann@example.com'])
        ->assertSessionHasErrors(['email' => 'There were too many tries from here. Please wait 60 seconds, then try again.']);
});

it('lets one address start thirty sign-ins with another service a minute', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    Socialite::fake('google');

    foreach (range(1, 30) as $ignored) {
        $this->get(route('connections.sign-in', ['provider' => 'google']))->assertRedirect('https://socialite.fake/google/authorize');
    }

    $this->get(route('connections.sign-in', ['provider' => 'google']))->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->get(route('connections.sign-in', ['provider' => 'google']))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

/**
 * The same Strava account, which shares no email, comes back from Strava, ready for the last step of signing up.
 */
function abuseLimitsStravaReturns(): void
{
    config(['services.strava.client_id' => 'strava-id', 'services.strava.client_secret' => 'strava-secret']);
    Socialite::fake('strava', (new SocialiteUser)
        ->map(['id' => '4711', 'name' => 'Ann Rider', 'email' => null])
        ->setApprovedScopes(['read']));

    test()->get(route('connections.sign-in', ['provider' => 'strava']));
    test()->get(route('connections.callback', ['provider' => 'strava', 'code' => 'from-strava', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));
}

it('emails three sign-up links an hour to one address, then says when to try again', function (): void {
    Mail::fake();
    $this->freezeTime();

    foreach (range(1, 3) as $ignored) {
        abuseLimitsStravaReturns();
        $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'Ann@example.com'])->assertSessionDoesntHaveErrors();
    }

    abuseLimitsStravaReturns();
    $this->from(route('connections.finish'))
        ->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => ' ann@example.com '])
        ->assertRedirect(route('connections.finish'))
        ->assertSessionHasErrors(['email' => 'Traceway sent several links already. Check your email, or wait 60 minutes, then try again.']);

    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.org'])->assertSessionDoesntHaveErrors();

    expect(Mail::queued(AccountMessage::class))->toHaveCount(4)
        ->and(Mail::queued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('ann@example.com')))->toHaveCount(3);
});

it('emails five sign-up links an hour for one account at a service', function (): void {
    Mail::fake();
    $this->freezeTime();

    foreach (range(1, 5) as $count) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$count]);
        abuseLimitsStravaReturns();
        $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann'.$count.'@example.com'])->assertSessionDoesntHaveErrors();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.6']);
    abuseLimitsStravaReturns();
    $this->from(route('connections.finish'))
        ->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann6@example.com'])
        ->assertSessionHasErrors(['email' => 'Traceway sent several links already. Check your email, or wait 60 minutes, then try again.']);

    expect(Mail::queued(AccountMessage::class))->toHaveCount(5);
});
