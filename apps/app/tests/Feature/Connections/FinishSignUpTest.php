<?php

declare(strict_types=1);

use App\Account\Role;
use App\Auth\Connections\LastSignIn;
use App\Auth\Connections\PendingSignUps;
use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * A new rider comes back from Google with an email Google did not confirm, or none, and lands on the last step.
 */
function finishSignUpArrive(?string $email = 'ann@gmail.com'): void
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => '2002', 'email' => $email, 'email_verified' => false])
        ->map(['id' => '2002', 'name' => 'Ann Rider', 'email' => $email])
        ->setApprovedScopes(['openid', 'email', 'profile']));

    test()->get(route('connections.sign-in', ['provider' => 'google']));
    test()->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));
}

/**
 * The same with Wahoo, which gives Traceway keys and never confirms an email. The keys are handed back to the
 * recording service.
 */
function finishSignUpArriveWithWahoo(): RecordingCourseService
{
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));

    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => 'road@wahoo.test'])
        ->setToken('access-new')
        ->setRefreshToken('refresh-new')
        ->setExpiresIn(7200)
        ->setApprovedScopes(['user_read', 'email', 'routes_read', 'routes_write']));

    test()->get(route('connections.sign-in', ['provider' => 'wahoo']));
    test()->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));

    return $wahoo;
}

/**
 * Gives the email and returns the link Traceway mailed for it. The browser keeps the cookie that marks it as the
 * one that asked for the link, as a real one does.
 */
function finishSignUpLink(string $name = 'Ann', string $email = 'ann@example.com'): string
{
    $response = test()->post(route('connections.finish.store'), ['name' => $name, 'email' => $email])->assertRedirect(route('login'));
    $browser = $response->getCookie(PendingSignUps::COOKIE)?->getValue();

    expect($browser)->toBeString();
    test()->withCookie(PendingSignUps::COOKIE, (string) $browser);

    $link = null;

    Mail::assertQueued(AccountMessage::class, function (AccountMessage $accountMessage) use (&$link): bool {
        $link = $accountMessage->values['url'] ?? null;

        return $accountMessage->template === 'connection-sign-up';
    });

    expect($link)->toBeString();

    return (string) $link;
}

it('asks a new rider for their email when the service did not confirm one', function (?string $email, string $shown): void {
    finishSignUpArrive($email);

    $this->get(route('connections.finish'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/finish-sign-up')
            ->where('service', ['slug' => 'google', 'label' => 'Google'])
            ->where('name', 'Ann Rider')
            ->where('email', $shown));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(ConnectedAccount::query()->count())->toBe(0);
})->with([
    'an email it did not confirm' => ['ann@gmail.com', 'ann@gmail.com'],
    'no email at all' => [null, ''],
]);

it('emails a link and opens no account until the rider uses it', function (): void {
    Mail::fake();
    Notification::fake();
    finishSignUpArrive(email: null);

    $this->post(route('connections.finish.store'), ['name' => ' Ann ', 'email' => ' Ann@Example.com '])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Check your email. A link to finish your account is on its way to ann@example.com.');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(ConnectedAccount::query()->count())->toBe(0)
        ->and(session('connections.pending'))->toBeNull();
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-sign-up'
        && $accountMessage->hasTo('ann@example.com')
        && $accountMessage->subjectLine === 'Finish your Traceway account'
        && $accountMessage->values['name'] === 'Ann'
        && $accountMessage->values['service'] === 'Google'
        && str_starts_with($accountMessage->values['url'], url('/login/finish/')));
    Mail::assertQueuedCount(1);
    Notification::assertNothingSent();
    $this->get(route('connections.finish'))->assertRedirect(route('login'));
});

it('shows the rider what the link opens without opening it, so a mail scanner does not use it up', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    $link = finishSignUpLink('Ann Rider', 'ann@example.com');

    $this->get($link)->assertOk();
    $this->get($link)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/finish-sign-up-confirm')
            ->where('service', ['slug' => 'google', 'label' => 'Google'])
            ->where('name', 'Ann Rider')
            ->where('email', 'ann@example.com')
            ->where('connects', expected: true)
            ->where('account', expected: null)
            ->has('token'));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('refuses an address that is not one, and keeps the sign-up waiting for a better one', function (string $email): void {
    Mail::fake();
    finishSignUpArrive(email: null);

    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => $email])
        ->assertSessionHasErrors('email');

    $this->get(route('connections.finish'))->assertOk();
    Mail::assertNothingQueued();
})->with([
    'no dot in the domain' => 'ann@gmail',
    'a letter outside ASCII' => 'jöe@example.com',
]);

it('names the account at the service that the link connects, but only in the browser that asked for it', function (): void {
    Mail::fake();
    finishSignUpArrive(email: 'someone@gmail.com');
    $link = finishSignUpLink('Ann', 'ann@example.com');

    $this->get($link)->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('connects', expected: true)
        ->where('account', 'someone@gmail.com'));

    $this->flushSession();
    $this->withCookie(PendingSignUps::COOKIE, '');

    $this->get($link)->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('connects', expected: false)
        ->where('account', expected: null));
});

it('opens the account with the email confirmed, connects the service, and logs the rider in when they use the link', function (): void {
    Mail::fake();
    Notification::fake();
    finishSignUpArrive(email: null);
    $link = finishSignUpLink('Ann', 'ann@example.com');

    // The session may have ended by the time the email comes. The browser's cookie is what counts.
    $this->flushSession();

    $this->post($link)
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, 'google')
        ->assertCookieExpired(PendingSignUps::COOKIE);

    $user = User::query()->sole();
    $connectedAccount = ConnectedAccount::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Ann')
        ->and($user->email)->toBe('ann@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->welcomed_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([Role::User->value]);
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider_user_id)->toBe('2002');
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome' && $accountMessage->hasTo('ann@example.com'));
    expect(Mail::queued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome'))->toHaveCount(1);
    Notification::assertNotSentTo($user, VerifyEmail::class);
});

it('counts the link as Traceway confirming the email, so a listed address becomes a Super Admin', function (): void {
    Mail::fake();
    config(['admin.super_admins' => ['ann@example.com']]);
    finishSignUpArrive(email: null);
    $link = finishSignUpLink('Ann', 'ann@example.com');

    $this->post($link)->assertRedirect(route('dashboard'));

    expect(User::query()->sole()->getRoleNames()->all())->toBe([Role::SuperAdmin->value]);
    expect(Mail::queued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome'))->toHaveCount(1);
});

it('turns away a link that was used already', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    $link = finishSignUpLink();

    $this->post($link)->assertRedirect(route('dashboard'));
    $this->flushSession();
    auth()->forgetGuards();

    $this->post($link)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That link has expired or was used. Choose the account again to sign up.']);
    $this->get($link)->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('turns away a link that ran out or was never sent', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    $link = finishSignUpLink();

    $this->travel(61)->minutes();

    $this->get($link)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That link has expired or was used. Choose the account again to sign up.']);
    $this->post($link)->assertRedirect(route('login'))->assertSessionHasErrors('connection');
    $this->post(route('connections.finish.open', ['token' => Str::random(64)]))->assertRedirect(route('login'))->assertSessionHasErrors('connection');
    $this->get(route('connections.finish.link', ['token' => 'not-a-token']))->assertRedirect(route('login'))->assertSessionHasErrors('connection');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('sends the rider to log in when the email got an account after the link was sent', function (): void {
    Mail::fake();
    $recordingCourseService = finishSignUpArriveWithWahoo();
    $link = finishSignUpLink('Grace', 'grace@example.com');
    $user = User::factory()->create(['email' => 'grace@example.com']);

    $this->get($link)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That email already has a Traceway account. Log in to it, then connect Wahoo in Settings.']);
    $this->post($link)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That email already has a Traceway account. Log in to it, then connect Wahoo in Settings.']);

    $this->assertGuest();
    expect(User::query()->sole()->is($user))->toBeTrue()
        ->and(ConnectedAccount::query()->count())->toBe(0)
        ->and($recordingCourseService->revoked)->toBe(['access-new']);
});

it('opens no account from the link while sign-up is closed', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    $link = finishSignUpLink();
    config(['fortify.features' => []]);

    $this->post($link)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'Traceway is not opening new accounts right now.']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('needs a name and an email', function (): void {
    finishSignUpArrive(email: null);

    $this->from(route('connections.finish'))
        ->post(route('connections.finish.store'), ['name' => '', 'email' => 'not an email'])
        ->assertRedirect(route('connections.finish'))
        ->assertSessionHasErrors(['name', 'email']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('does not connect a service to an account by an email the rider only typed: they log in, then say yes', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'grace@example.com']);
    finishSignUpArrive(email: null);

    $this->post(route('connections.finish.store'), ['name' => 'Grace', 'email' => 'Grace@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionDoesntHaveErrors();

    $this->assertGuest();
    expect(User::query()->sole()->is($user))->toBeTrue()
        ->and(ConnectedAccount::query()->count())->toBe(0)
        ->and(session('connections.pending'))->toBeNull();
    Mail::assertNothingQueued();
    $this->get(route('login'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('pendingConnection.confirm', expected: true));

    $this->post(route('login.store'), ['email' => 'grace@example.com', 'password' => 'password'])->assertRedirect(route('connections.pending.show'));
    expect(ConnectedAccount::query()->count())->toBe(0);

    $this->post(route('connections.pending.store'))->assertRedirect(route('dashboard'));
    expect(ConnectedAccount::query()->sole()->user_id)->toBe($user->id);
});

it('hands back the keys a service gave when the rider with the typed email says not now', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'grace@example.com']);
    $recordingCourseService = finishSignUpArriveWithWahoo();

    $this->post(route('connections.finish.store'), ['name' => 'Grace', 'email' => 'grace@example.com'])
        ->assertRedirect(route('login'));
    expect($recordingCourseService->revoked)->toBe([]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('connections.pending.show'));
    $this->delete(route('connections.pending.destroy'))->assertRedirect(route('dashboard'));

    expect($recordingCourseService->revoked)->toBe(['access-new'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('starts over when nothing is waiting to finish', function (string $method): void {
    $this->call($method, route('connections.finish'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'That took too long. Choose the account again to sign up.']);

    expect(User::query()->count())->toBe(0);
})->with([
    'the page' => 'GET',
    'the form' => 'POST',
]);

it('forgets the waiting account when the rider cancels, and hands back the keys the service gave', function (): void {
    $recordingCourseService = finishSignUpArriveWithWahoo();

    $this->delete(route('connections.finish.cancel'))->assertRedirect(route('login'));

    $this->get(route('connections.finish'))->assertRedirect(route('login'));
    expect(User::query()->count())->toBe(0)
        ->and($recordingCourseService->revoked)->toBe(['access-new']);
});

it('keeps the keys while a connection carried over from before could be the same account', function (): void {
    $recordingCourseService = finishSignUpArriveWithWahoo();
    ConnectedAccount::factory()->withoutIdentity()->create(['access_token' => 'access-carried-over']);

    $this->delete(route('connections.finish.cancel'))->assertRedirect(route('login'));

    expect($recordingCourseService->revoked)->toBe([]);
});

it('lets the waiting account run out after fifteen minutes', function (): void {
    finishSignUpArrive();

    $this->travel(16)->minutes();

    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('connection');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('marks the browser that asked for the link with a cookie for as long as the link works', function (): void {
    Mail::fake();
    $this->freezeTime();
    finishSignUpArrive(email: null);

    $cookie = $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertCookie(PendingSignUps::COOKIE)
        ->getCookie(PendingSignUps::COOKIE);

    expect($cookie?->isHttpOnly())->toBeTrue()
        ->and($cookie?->getSameSite())->toBe('lax')
        ->and($cookie?->getPath())->toBe('/')
        ->and($cookie?->getExpiresTime())->toBe(now()->addMinutes(PendingSignUps::MINUTES)->getTimestamp())
        ->and($cookie?->getValue())->toMatch('/^[A-Za-z0-9]{64}$/');
});

it('opens the account without the service when the link is used in another browser, and hands back its keys', function (): void {
    Mail::fake();
    config(['admin.super_admins' => ['grace@example.com']]);
    $recordingCourseService = finishSignUpArriveWithWahoo();
    $link = finishSignUpLink('Grace', 'grace@example.com');

    // The owner of the address opens the email somewhere else, without the cookie.
    $this->flushSession();
    $this->withCookie(PendingSignUps::COOKIE, '');

    $this->get($link)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/finish-sign-up-confirm')
            ->where('connects', expected: false));

    $this->freezeTime();
    $this->post($link)
        ->assertRedirect(route('dashboard'))
        ->assertCookieMissing(LastSignIn::COOKIE)
        ->assertCookieExpired(PendingSignUps::COOKIE)
        ->assertInertiaFlash('toast', ['type' => 'info', 'message' => 'Your account is open. To log in with Wahoo, connect it in Settings, under Connections.'])
        ->assertSessionHas('auth.password_confirmed_at', now()->getTimestamp());

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->email)->toBe('grace@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([Role::SuperAdmin->value])
        ->and(ConnectedAccount::query()->count())->toBe(0)
        ->and($recordingCourseService->revoked)->toBe(['access-new']);
    expect(Mail::queued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome' && $accountMessage->hasTo('grace@example.com')))->toHaveCount(1);

    // The other person's Wahoo has no way into it.
    auth()->logout();
    $this->flushSession();
    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => 'road@wahoo.test'])
        ->setToken('access-later')
        ->setApprovedScopes(['user_read', 'email', 'routes_read', 'routes_write']));
    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']));

    $this->assertGuest();
});

it('says a link is on its way when the form is sent again after the link went out', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    finishSignUpLink('Ann', 'ann@example.com');

    // Back brings the page out of the browser's history, and the rider sends it again.
    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'A link is already on its way to a••@example.com. Check your email.')
        ->assertSessionDoesntHaveErrors();
    $this->get(route('connections.finish'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'A link is already on its way to a••@example.com. Check your email.');

    Mail::assertQueuedCount(1);

    // Once that link has stopped working, the sign-up has run out.
    $this->travel(PendingSignUps::MINUTES + 1)->minutes();

    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertSessionHasErrors(['connection' => 'That took too long. Choose the account again to sign up.']);
});

it('forgets a link that went out when the rider starts another sign-up', function (): void {
    Mail::fake();
    finishSignUpArrive(email: null);
    finishSignUpLink('Ann', 'ann@example.com');

    finishSignUpArrive(email: null);

    $this->get(route('connections.finish'))->assertOk();
    expect(session('connections.sent'))->toBeNull();
});

it('hands back the keys and opens nothing when sign-up closed while the rider filled the form', function (): void {
    Mail::fake();
    $recordingCourseService = finishSignUpArriveWithWahoo();
    config(['fortify.features' => []]);

    $this->post(route('connections.finish.store'), ['name' => 'Grace', 'email' => 'grace@example.com'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['connection' => 'Traceway is not opening new accounts right now.']);

    expect(session('connections.pending'))->toBeNull()
        ->and($recordingCourseService->revoked)->toBe(['access-new'])
        ->and(User::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('gives the finish page the public key for the person check', function (): void {
    config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    finishSignUpArrive();

    $this->get(route('connections.finish'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('turnstileSiteKey', 'site-key'));
});
