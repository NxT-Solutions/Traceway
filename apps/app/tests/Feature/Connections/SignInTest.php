<?php

declare(strict_types=1);

use App\Account\Role;
use App\Auth\Connections\LastSignIn;
use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Gives Traceway keys for these services, which turns them on.
 */
function signInWithServices(string ...$slugs): void
{
    foreach ($slugs as $slug) {
        config(["services.{$slug}.client_id" => $slug.'-id', "services.{$slug}.client_secret" => $slug.'-secret']);
    }
}

/**
 * Has Google answer the next sign-in with this account.
 */
function signInGoogleAnswers(string $id = '1001', ?string $email = 'ann@gmail.com', bool $verified = true): void
{
    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Ann Rider', 'email' => $email])
        ->setApprovedScopes(['openid', 'email', 'profile']));
}

function signInGoogleLinkedTo(User $user, string $id = '1001'): ConnectedAccount
{
    return ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => $id]);
}

/**
 * Google sends the rider back with a code, as it does once they allow Traceway.
 */
function signInGoogleReturns(): TestResponse
{
    return test()->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']));
}

it('sends a guest to the service to log in, and notes why', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();

    $this->get(route('connections.sign-in', ['provider' => 'google', 'remember' => 1]))
        ->assertRedirect('https://socialite.fake/google/authorize');

    expect(session('connections.flow'))->toMatchArray([
        'intent' => 'sign_in',
        'provider' => 'google',
        'rider' => null,
        'remember' => true,
        'return' => null,
    ]);
    $this->assertGuest();
});

it('does not know a service that is not set up here', function (string $provider): void {
    signInWithServices('google');

    $this->get('/auth/'.$provider.'/redirect')->assertNotFound();

    expect(session('connections.flow'))->toBeNull();
})->with([
    'a service without keys' => 'github',
    'a service Traceway does not offer' => 'myspace',
]);

it('sends a rider who is already logged in to the planner instead', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();

    $this->actingAs(User::factory()->create())
        ->get(route('connections.sign-in', ['provider' => 'google']))
        ->assertRedirect(route('dashboard'));

    expect(session('connections.flow'))->toBeNull();
});

it('refuses an answer for a sign-in that did not start here, even for an account that is connected', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();
    signInGoogleLinkedTo(User::factory()->create());

    signInGoogleReturns()
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That took too long, or it started in another tab. Try again.']);

    $this->assertGuest();
    expect(ConnectedAccount::query()->sole()->last_signed_in_at)->toBeNull();
});

it('says the sign-in was cancelled when the rider said no at the service', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();
    signInGoogleLinkedTo(User::factory()->create());

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    $this->get(route('connections.callback', ['provider' => 'google', 'error' => 'access_denied', 'state' => 'state']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'Google did not connect, because it was cancelled there.']);

    $this->assertGuest();
    expect(ConnectedAccount::query()->sole()->last_signed_in_at)->toBeNull();
});

it('logs in the rider the account belongs to with a new session, and goes on to the page they asked for', function (array $session, string $destination): void {
    $this->freezeTime();
    signInWithServices('google');
    signInGoogleAnswers();
    $user = User::factory()->create();
    signInGoogleLinkedTo($user);

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    $sessionId = session()->getId();

    $this->withSession($session)
        ->withCookie((string) config('session.cookie'), $sessionId)
        ->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect($destination)
        ->assertCookie(LastSignIn::COOKIE, 'google');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId)
        ->and(ConnectedAccount::query()->sole()->last_signed_in_at?->getTimestamp())->toBe(now()->getTimestamp());
})->with([
    "an AI assistant's approval page" => [fn (): array => ['url.intended' => url('/oauth/authorize?client_id=claude')], fn (): string => url('/oauth/authorize?client_id=claude')],
    'no page' => [[], fn (): string => route('dashboard')],
]);

it('keeps the rider logged in on this browser when they asked to be remembered', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();
    $user = User::factory()->create();
    signInGoogleLinkedTo($user);

    $this->get(route('connections.sign-in', ['provider' => 'google', 'remember' => 1]));
    signInGoogleReturns()
        ->assertRedirect(route('dashboard'))
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
});

it('asks a rider with two-factor sign-in for a code, and remembers the service they logged in with', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();
    $user = User::factory()->withTwoFactor()->create();
    signInGoogleLinkedTo($user);

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, 'google');

    $this->assertAuthenticatedAs($user);
});

it('marks the password as last used when a rider leaves a service for their password', function (): void {
    signInWithServices('google');
    signInGoogleAnswers();
    $user = User::factory()->withTwoFactor()->create();
    signInGoogleLinkedTo($user);

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()->assertRedirect(route('two-factor.login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, LastSignIn::PASSWORD);

    $this->assertAuthenticatedAs($user);
});

it('opens an account without a password for a new rider whose email the service confirmed', function (): void {
    Mail::fake();
    Notification::fake();
    config(['admin.super_admins' => ['ann@gmail.com']]);
    signInWithServices('google');
    signInGoogleAnswers('1001', 'Ann@Gmail.com');

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, 'google');

    $user = User::query()->sole();
    $connectedAccount = ConnectedAccount::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBe('ann@gmail.com')
        ->and($user->name)->toBe('Ann Rider')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([Role::User->value]);
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider)->toBe('google')
        ->and($connectedAccount->provider_user_id)->toBe('1001')
        ->and($connectedAccount->account_label)->toBe('Ann@Gmail.com')
        ->and($connectedAccount->access_token)->toBeNull();
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome' && $accountMessage->hasTo('ann@gmail.com'));
    Notification::assertNotSentTo($user, VerifyEmail::class);
});

it('opens no account while sign-up is closed', function (): void {
    Mail::fake();
    config(['fortify.features' => []]);
    signInWithServices('google');
    signInGoogleAnswers();

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'Traceway is not opening new accounts right now.']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('still asks an existing rider to log in to connect while sign-up is closed, since no account opens', function (): void {
    Mail::fake();
    config(['fortify.features' => []]);
    signInWithServices('google');
    signInGoogleAnswers('1001', 'ann@gmail.com');
    User::factory()->create(['email' => 'ann@gmail.com']);

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()
        ->assertRedirect(route('login'))
        ->assertSessionDoesntHaveErrors();

    $this->assertGuest();
    expect(session('connections.link'))->toBeString()
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('does not hold a service for an email it did not confirm that has an account, and keeps nothing waiting', function (): void {
    Mail::fake();
    signInWithServices('google');
    signInGoogleAnswers('1001', 'ann@gmail.com', verified: false);
    $user = User::factory()->create(['email' => 'ann@gmail.com']);

    $this->get(route('connections.sign-in', ['provider' => 'google']));
    signInGoogleReturns()
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That email already has a Traceway account. Log in to it, then connect Google in Settings.']);

    expect(session('connections.link'))->toBeNull()
        ->and(session('connections.pending'))->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

/**
 * Has Wahoo, which gives Traceway keys and never confirms an email, answer the next sign-in. Keys handed back go
 * to the recording service.
 */
function signInWahooAnswers(string $email = 'road@wahoo.test'): RecordingCourseService
{
    signInWithServices('wahoo');
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));

    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => $email])
        ->setToken('access-new')
        ->setRefreshToken('refresh-new')
        ->setExpiresIn(7200)
        ->setApprovedScopes(['user_read', 'email', 'routes_read', 'routes_write']));

    return $wahoo;
}

it('hands back the keys a service gave when its email has an account and the service did not confirm it', function (): void {
    $recordingCourseService = signInWahooAnswers('ann@example.com');
    User::factory()->create(['email' => 'ann@example.com']);

    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That email already has a Traceway account. Log in to it, then connect Wahoo in Settings.']);

    expect($recordingCourseService->revoked)->toBe(['access-new'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('hands back the keys when the rider logged in another way before the service answered', function (): void {
    $recordingCourseService = signInWahooAnswers();

    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->actingAs(User::factory()->create())
        ->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('dashboard'));

    expect($recordingCourseService->revoked)->toBe(['access-new'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('keeps the keys of an account a rider connected when an answer for it goes nowhere', function (): void {
    $recordingCourseService = signInWahooAnswers();
    $owner = User::factory()->create();
    ConnectedAccount::factory()->for($owner)->create(['provider_user_id' => '60462', 'access_token' => 'access-kept']);

    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->actingAs(User::factory()->create())
        ->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('dashboard'));

    expect($recordingCourseService->revoked)->toBe([]);
});
