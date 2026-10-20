<?php

declare(strict_types=1);

use App\Account\ConnectedIdentity;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\Email;
use App\Auth\Connections\ConnectionFlow;
use App\Auth\Connections\LastSignIn;
use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Comes back from Google with a confirmed email that already has a Traceway account.
 */
function pendingConnectionArrive(string $email): void
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => '3003', 'email' => $email, 'email_verified' => true])
        ->map(['id' => '3003', 'name' => 'Noah Rider', 'email' => $email])
        ->setApprovedScopes(['openid', 'email', 'profile']));

    test()->get(route('connections.sign-in', ['provider' => 'google']));
    test()->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']))
        ->assertRedirect(route('login'));
}

/**
 * A service holding keys waits in this browser, as one would once a service that gives keys confirms emails. No
 * such service confirms one today, so the identity goes in the session directly.
 */
function pendingConnectionWithKeys(string $email): RecordingCourseService
{
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));

    test()->get(route('login'));
    new ConnectionFlow(resolve('session.store'))->awaitLogin(
        new ConnectedIdentity(ConnectionProvider::Wahoo, '60462', new Email($email), emailVerified: true, connectionTokens: new ConnectionTokens('access-waiting', 'refresh-waiting', now()->addHour()->getTimestamp())),
        new Email($email),
    );

    return $wahoo;
}

function pendingConnectionLogIn(User $user): void
{
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
}

it('does not connect an account by its email alone, and asks the rider to log in first', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'noah.rider@gmail.com']);

    pendingConnectionArrive('noah.rider@gmail.com');

    $this->assertGuest();
    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/login')
            ->where('pendingConnection', ['slug' => 'google', 'label' => 'Google', 'email' => 'n••••••@gmail.com']));
});

it('connects the service once the rider logs in to the account with that email', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->post(route('login.store'), ['email' => 'noah.rider@gmail.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, LastSignIn::PASSWORD)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google connected. Next time you can log in with it.']);

    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider)->toBe('google')
        ->and($connectedAccount->provider_user_id)->toBe('3003');
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added'
        && $accountMessage->hasTo('noah.rider@gmail.com')
        && $accountMessage->values['service'] === 'Google');
    expect(session('connections.link'))->toBeNull();
});

it('connects nothing when the rider logs in to a different account', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'noah.rider@gmail.com']);
    $user = User::factory()->create(['email' => 'someone.else@example.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->post(route('login.store'), ['email' => 'someone.else@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', ['type' => 'info', 'message' => 'Google was not connected, because you logged in to a different account. Connect it in Settings.']);

    $this->assertAuthenticatedAs($user);
    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('forgets the waiting service after fifteen minutes', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->travel(16)->minutes();

    $this->get(route('login'))->assertInertia(fn (Assert $assert): Assert => $assert->where('pendingConnection', expected: null));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlashMissing('toast');

    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('drops the waiting service when the rider says not to connect it', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->delete(route('connections.pending.destroy'))->assertRedirect(route('login'));

    $this->get(route('login'))->assertInertia(fn (Assert $assert): Assert => $assert->where('pendingConnection', expected: null));
    pendingConnectionLogIn($user);
    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('hands back the keys of a waiting service when the rider says not to connect it', function (): void {
    $recordingCourseService = pendingConnectionWithKeys('noah.rider@gmail.com');

    $this->delete(route('connections.pending.destroy'))->assertRedirect(route('login'));

    expect($recordingCourseService->revoked)->toBe(['access-waiting'])
        ->and(session('connections.link'))->toBeNull();
});

it('hands back the keys of a waiting service when the rider logs in to a different account', function (): void {
    $user = User::factory()->create(['email' => 'someone.else@example.com']);
    $recordingCourseService = pendingConnectionWithKeys('noah.rider@gmail.com');

    pendingConnectionLogIn($user);

    expect($recordingCourseService->revoked)->toBe(['access-waiting'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('drops an unfinished sign-up when the rider logs in another way, and hands back its keys', function (): void {
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));
    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => 'road@wahoo.test'])
        ->setToken('access-new')
        ->setRefreshToken('refresh-new')
        ->setExpiresIn(7200));
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);

    $this->get(route('connections.sign-in', ['provider' => 'wahoo']));
    $this->get(route('connections.callback', ['provider' => 'wahoo', 'code' => 'from-wahoo', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));

    pendingConnectionLogIn($user);

    expect(session('connections.pending'))->toBeNull()
        ->and($wahoo->revoked)->toBe(['access-new'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});
