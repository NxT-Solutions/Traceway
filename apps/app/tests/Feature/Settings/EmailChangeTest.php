<?php

declare(strict_types=1);

use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

/**
 * The link Traceway mails to the new email the rider asked for.
 */
function emailChangeLink(User $user): string
{
    $pending = $user->refresh()->pending_email;

    return new ConfirmNewEmail($user->id, $user->name, (string) $pending)->url();
}

function emailChangeWaiting(string $email = 'ann@example.com', ?string $pending = 'ann@example.org'): User
{
    return User::factory()->create(['email' => $email, 'pending_email' => $pending]);
}

it('asks for the password before a new email, and refuses a wrong one', function (string $password, string $error): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ann@example.org', 'current_password' => $password])
        ->assertSessionHasErrors(['current_password' => $error]);

    expect($user->refresh()->pending_email)->toBeNull()
        ->and($user->email)->toBe('ann@example.com');
    Notification::assertNothingSent();
})->with([
    'no password' => ['', 'Enter your password to change your email.'],
    'a wrong password' => ['not-it', 'The password is incorrect.'],
]);

it('asks a rider without a password to confirm it is them first', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ann@example.com', 'password' => null]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ann@example.org'])
        ->assertSessionHasErrors(['current_password' => 'Confirm it is you first, then change your email.']);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ann@example.org'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->pending_email)->toBe('ann@example.org');
    Notification::assertSentOnDemandTimes(ConfirmNewEmail::class, 1);
});

it('lets the password typed on the profile count as confirming it is them', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'ann@example.org', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(session('auth.password_confirmed_at'))->toBeInt();
});

it('saves a new name without asking for anything, and leaves a waiting email alone', function (): void {
    $user = emailChangeWaiting();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Ann B', 'email' => 'ann@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Ann B')
        ->and($user->pending_email)->toBe('ann@example.org');
});

it('shows the waiting email on the profile page', function (): void {
    $user = emailChangeWaiting();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('pendingEmail', 'ann@example.org'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->missing('auth.user.pending_email'));
});

it('swaps the new email in, confirmed, when the rider opens the link, and tells the old address', function (): void {
    Mail::fake();
    Event::fake([Verified::class]);
    $user = emailChangeWaiting();
    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user)
        ->get(emailChangeLink($user))
        ->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('toast.type', 'success');

    $user->refresh();
    expect($user->email)->toBe('ann@example.org')
        ->and($user->pending_email)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('ann@example.com')
        && $accountMessage->template === 'email-changed'
        && $accountMessage->values['new_email'] === 'ann@example.org'
        && $accountMessage->values['url'] === rtrim(config()->string('app.url'), '/').'/support');
    Event::assertDispatched(fn (Verified $verified): bool => $verified->user->getKey() === $user->id);
});

it('refuses a link that ran out, and changes nothing', function (): void {
    $user = emailChangeWaiting();
    $link = emailChangeLink($user);

    $this->travel(61)->minutes();

    $this->actingAs($user)
        ->get($link)
        ->assertRedirect(route('profile.edit'))
        ->assertInertiaFlash('toast.message', 'That link has run out. Send it again from your profile.');

    expect($user->refresh()->email)->toBe('ann@example.com');
});

it('refuses a link that was sent to another rider', function (): void {
    $user = emailChangeWaiting();

    $this->actingAs(User::factory()->create())
        ->get(emailChangeLink($user))
        ->assertForbidden();

    expect($user->refresh()->email)->toBe('ann@example.com');
});

it('refuses a link once the change was replaced or cancelled', function (): void {
    $user = emailChangeWaiting();
    $link = emailChangeLink($user);

    $this->actingAs($user)->delete(route('profile.email.cancel'))->assertRedirect(route('profile.edit'));

    $this->actingAs($user)
        ->get($link)
        ->assertInertiaFlash('toast.message', 'That link is for an email change that was cancelled or replaced.');

    $user->forceFill(['pending_email' => 'ann@example.net'])->save();

    $this->actingAs($user)
        ->get($link)
        ->assertInertiaFlash('toast.message', 'That link is for an email change that was cancelled or replaced.');

    expect($user->refresh()->email)->toBe('ann@example.com');
});

it('keeps the email when another account took it in the meantime', function (): void {
    Mail::fake();
    $user = emailChangeWaiting();
    $link = emailChangeLink($user);
    User::factory()->create(['email' => 'ann@example.org']);

    $this->actingAs($user)
        ->get($link)
        ->assertInertiaFlash('toast.message', 'Another Traceway account uses that email now, so yours did not change.');

    expect($user->refresh()->email)->toBe('ann@example.com')
        ->and($user->pending_email)->toBeNull();
    Mail::assertNothingQueued();
});

it('sends the link again on request', function (): void {
    Notification::fake();
    $user = emailChangeWaiting();

    $this->actingAs($user)->post(route('profile.email.resend'))->assertRedirect(route('profile.edit'));

    Notification::assertSentOnDemand(ConfirmNewEmail::class, fn (ConfirmNewEmail $confirmNewEmail, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'ann@example.org');
});

it('builds the mailed link from the site address, whatever host the request named', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->actingAs($user)
        ->withHeaders(['Host' => 'evil.test', 'X-Forwarded-Host' => 'evil.test'])
        ->patch('http://evil.test/settings/profile', ['name' => $user->name, 'email' => 'ann@example.org', 'current_password' => 'password']);

    Notification::assertSentOnDemand(ConfirmNewEmail::class, fn (ConfirmNewEmail $confirmNewEmail): bool => str_starts_with($confirmNewEmail->url(), rtrim(config()->string('app.url'), '/').'/settings/profile/email/'));
});

it('drops a waiting email when an admin changes the email', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $user = emailChangeWaiting();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user->id), ['name' => $user->name, 'email' => 'ann@example.net'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('ann@example.net')
        ->and($user->pending_email)->toBeNull();
});

it('does not let someone move their account onto an address they do not read, so its owner can still sign up', function (): void {
    Notification::fake();
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
    $mallory = User::factory()->create(['email' => 'mallory@example.com']);
    ConnectedAccount::factory()->for($mallory)->signInOnly('google')->create(['provider_user_id' => '999']);

    $this->actingAs($mallory)
        ->patch(route('profile.update'), ['name' => $mallory->name, 'email' => 'victim@example.com', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();
    auth()->logout();

    expect($mallory->refresh()->email)->toBe('mallory@example.com');

    $this->post(route('register.store'), [
        'name' => 'Victim',
        'email' => 'victim@example.com',
        'password' => 'victim-password',
        'password_confirmation' => 'victim-password',
    ])->assertSessionHasNoErrors();
    $user = User::query()->where('email', 'victim@example.com')->firstOrFail();
    auth()->logout();

    Socialite::fake('google', (new SocialiteUser)->map(['id' => '999', 'name' => 'Mallory', 'email' => null]));
    $this->get(route('connections.sign-in', ['provider' => 'google']));
    $this->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']));

    $this->assertAuthenticatedAs($mallory);
    expect(auth()->id())->not->toBe($user->id);
});

it('signs the rider out everywhere when the password is reset', function (): void {
    $user = User::factory()->create(['remember_token' => 'remember-before']);
    DB::table('sessions')->insert(['id' => 'somewhere-else', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->remember_token)->not->toBe('remember-before');
});

it('keeps the waiting email in the copy of the rider data', function (): void {
    Mail::fake();
    $user = emailChangeWaiting();

    $document = $this->actingAs($user)->get(route('privacy.export'))->assertOk()->json();

    expect($document['account']['pending_email'] ?? null)->toBe('ann@example.org');
});
