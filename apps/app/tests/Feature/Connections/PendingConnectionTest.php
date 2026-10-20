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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Comes back from Google with a confirmed email.
 */
function pendingConnectionGoogle(string $email): TestResponse
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => '3003', 'email' => $email, 'email_verified' => true])
        ->map(['id' => '3003', 'name' => 'Noah Rider', 'email' => $email])
        ->setApprovedScopes(['openid', 'email', 'profile']));

    test()->get(route('connections.sign-in', ['provider' => 'google']));

    return test()->get(route('connections.callback', ['provider' => 'google', 'code' => 'from-google', 'state' => 'state']));
}

/**
 * Comes back from Discord with an email it has not verified, which anyone could have typed there.
 */
function pendingConnectionArrive(string $email): void
{
    config(['services.discord.client_id' => 'discord-id', 'services.discord.client_secret' => 'discord-secret']);

    Socialite::fake('discord', (new SocialiteUser)
        ->setRaw(['id' => '4004', 'email' => $email, 'verified' => false])
        ->map(['id' => '4004', 'nickname' => 'noahrides', 'name' => 'Noah Rider', 'email' => $email])
        ->setApprovedScopes(['identify', 'email']));

    test()->get(route('connections.sign-in', ['provider' => 'discord']));
    test()->get(route('connections.callback', ['provider' => 'discord', 'code' => 'from-discord', 'state' => 'state']))
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

function pendingConnectionLogIn(User $user, ?string $to = null): void
{
    test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($to ?? route('dashboard'));
}

it('logs the owner of an email the service confirmed in, and connects the service at once', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);

    pendingConnectionGoogle('noah.rider@gmail.com')
        ->assertRedirect(route('dashboard'))
        ->assertCookie(LastSignIn::COOKIE, 'google')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google connected. Next time you can log in with it.']);

    $this->assertAuthenticatedAs($user);
    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider)->toBe('google')
        ->and($connectedAccount->provider_user_id)->toBe('3003')
        ->and(session('connections.link'))->toBeNull();
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added'
        && $accountMessage->hasTo('noah.rider@gmail.com')
        && $accountMessage->values['service'] === 'Google');
});

it('asks for two-step sign-in first, and connects the service only once it is answered', function (): void {
    Mail::fake();
    $user = User::factory()->withTwoFactor()->create(['email' => 'noah.rider@gmail.com']);

    pendingConnectionGoogle('noah.rider@gmail.com')->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
    expect(ConnectedAccount::query()->count())->toBe(0);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(ConnectedAccount::query()->sole()->provider)->toBe('google');
});

it('takes back an account nobody confirmed when the service proves its email: every other way in goes, the password too', function (): void {
    Mail::fake();
    $user = User::factory()->unverified()->create(['email' => 'noah.rider@gmail.com']);
    $left = ConnectedAccount::factory()->for($user)->signInOnly('discord')->create(['provider_user_id' => '666']);
    DB::table('passkeys')->insert(['user_id' => $user->id, 'name' => 'Not Noah', 'credential_id' => 'credential-left', 'credential' => '{}', 'created_at' => now(), 'updated_at' => now()]);

    pendingConnectionGoogle('noah.rider@gmail.com')->assertRedirect(route('dashboard'));

    $user->refresh();
    $this->assertAuthenticatedAs($user);
    $this->assertModelMissing($left);
    expect($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(DB::table('passkeys')->where('user_id', $user->id)->count())->toBe(0)
        ->and(ConnectedAccount::query()->sole()->provider)->toBe('google');
});

it('confirms the email of a rider who confirmed one before, and keeps their ways in', function (): void {
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    $user->forceFill(['email_verified_at' => null])->save();
    $kept = ConnectedAccount::factory()->for($user)->signInOnly('discord')->create(['provider_user_id' => '777']);

    pendingConnectionGoogle('noah.rider@gmail.com')->assertRedirect(route('dashboard'));

    $user->refresh();
    $this->assertModelExists($kept);
    expect($user->password)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(ConnectedAccount::query()->where('provider', 'google')->count())->toBe(1);
});

it('asks the rider to log in, then to say yes, when the service did not confirm the email', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->assertGuest();
    expect(ConnectedAccount::query()->count())->toBe(0);
    $this->get(route('login'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('pendingConnection', ['slug' => 'discord', 'label' => 'Discord', 'email' => 'n••••••@gmail.com', 'confirm' => true]));

    pendingConnectionLogIn($user, route('connections.pending.show'));
    expect(ConnectedAccount::query()->count())->toBe(0);

    $this->get(route('connections.pending.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/connect-pending')
            ->where('service', ['slug' => 'discord', 'label' => 'Discord'])
            ->where('account', 'noahrides')
            ->where('email', 'noah.rider@gmail.com'));

    $this->post(route('connections.pending.store'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Discord connected. Next time you can log in with it.']);

    expect(ConnectedAccount::query()->sole()->user_id)->toBe($user->id);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added');
});

it('connects nothing when the rider who logged in says not now', function (): void {
    Mail::fake();
    User::factory()->create(['email' => 'noah.rider@gmail.com']);
    $user = User::factory()->create(['email' => 'someone.else@example.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    pendingConnectionLogIn($user, route('connections.pending.show'));
    $this->delete(route('connections.pending.destroy'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and(session('connections.link'))->toBeNull();
    Mail::assertNothingQueued();
});

it('goes on to the page the rider was on the way to once they answer', function (): void {
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');
    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    pendingConnectionLogIn($user, route('connections.pending.show'));

    $this->post(route('connections.pending.store'))->assertRedirect(route('profile.edit'));
});

it('lets a rider whose service shares no email connect it to the account they already have', function (): void {
    config(['services.openstreetmap.client_id' => 'osm-id', 'services.openstreetmap.client_secret' => 'osm-secret']);
    Socialite::fake('openstreetmap', (new SocialiteUser)->map(['id' => '5005', 'nickname' => 'noahmaps', 'name' => 'Noah Rider', 'email' => null]));
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);

    $this->get(route('connections.sign-in', ['provider' => 'openstreetmap']));
    $this->get(route('connections.callback', ['provider' => 'openstreetmap', 'code' => 'from-osm', 'state' => 'state']))
        ->assertRedirect(route('connections.finish'));
    $this->post(route('connections.finish.existing'))->assertRedirect(route('login'));

    $this->get(route('login'))->assertInertia(fn (Assert $assert): Assert => $assert
        ->where('pendingConnection', ['slug' => 'openstreetmap', 'label' => 'OpenStreetMap', 'email' => null, 'confirm' => true]));
    pendingConnectionLogIn($user, route('connections.pending.show'));
    $this->post(route('connections.pending.store'))->assertRedirect(route('dashboard'));

    expect(ConnectedAccount::query()->sole()->provider_user_id)->toBe('5005')
        ->and(session('connections.pending'))->toBeNull();
});

it('forgets the waiting service after fifteen minutes', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'noah.rider@gmail.com']);
    pendingConnectionArrive('noah.rider@gmail.com');

    $this->travel(16)->minutes();

    $this->get(route('login'))->assertInertia(fn (Assert $assert): Assert => $assert->where('pendingConnection', expected: null));
    pendingConnectionLogIn($user);

    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('drops the waiting service when the rider says not to connect it before logging in', function (): void {
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
