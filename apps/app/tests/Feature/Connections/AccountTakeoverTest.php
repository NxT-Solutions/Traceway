<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\DeviceLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Someone else's Strava account, which shares no email, comes back from Strava.
 */
function takeoverStravaReturns(): void
{
    config(['services.strava.client_id' => 'strava-id', 'services.strava.client_secret' => 'strava-secret']);

    Socialite::fake('strava', (new SocialiteUser)
        ->map(['id' => '777', 'name' => 'Not Ann', 'email' => null])
        ->setApprovedScopes(['read']));

    test()->get(route('connections.sign-in', ['provider' => 'strava']));
    test()->get(route('connections.callback', ['provider' => 'strava', 'code' => 'from-strava', 'state' => 'state']));
}

function takeoverResetPassword(User $user, string $password = 'owner-password'): void
{
    test()->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasNoErrors();
}

it('opens no account and connects nothing when someone signs up with an address that is not theirs', function (): void {
    Mail::fake();

    takeoverStravaReturns();
    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-sign-up'
        && $accountMessage->hasTo('ann@example.com')
        && $accountMessage->values['service'] === 'Strava');

    // The owner of the address opens their own account, and the other person's Strava has no way into it.
    $this->post(route('register.store'), ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertRedirect(route('dashboard'));
    auth()->logout();
    $this->flushSession();

    takeoverStravaReturns();

    $this->assertGuest();
    expect(ConnectedAccount::query()->count())->toBe(0);
});

it('does not hand a service to whoever logs in next on a shared browser with the address someone typed', function (): void {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'ann@example.com']);

    takeoverStravaReturns();
    $this->post(route('connections.finish.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])
        ->assertSessionHasErrors('connection');

    $this->post(route('login.store'), ['email' => 'ann@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($owner);
    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();

    auth()->logout();
    $this->flushSession();
    takeoverStravaReturns();

    $this->assertGuest();
});

it('lets an account whose email nobody confirmed add no service to log in with', function (): void {
    config(['services.strava.client_id' => 'strava-id', 'services.strava.client_secret' => 'strava-secret']);

    $this->post(route('register.store'), ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password', 'password_confirmation' => 'password']);
    $this->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('connections.connect', ['provider' => 'strava']))
        ->assertRedirect(route('verification.notice'));

    expect(session('connections.flow'))->toBeNull()
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('takes every way in off an unconfirmed account when the owner of the address resets the password', function (): void {
    Mail::fake();
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));
    $user = User::factory()->unverified()->withTwoFactor()->create(['email' => 'ann@example.com', 'remember_token' => 'remember-before']);
    ConnectedAccount::factory()->for($user)->signInOnly('strava')->create(['provider_user_id' => '777']);
    ConnectedAccount::factory()->for($user)->create(['provider_user_id' => '60462', 'access_token' => 'access-left', 'expires_at' => now()->addHour()]);
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Not Ann',
        'credential_id' => 'credential-left',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DeviceLink::factory()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'watch-left'), 'label' => 'Not Ann']);
    DB::table('oauth_clients')->insert(['id' => $clientId = (string) Str::uuid(), 'name' => 'Not Ann', 'redirect_uris' => '[]', 'grant_types' => '["authorization_code"]', 'revoked' => false, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('oauth_access_tokens')->insert(['id' => $tokenId = Str::random(80), 'user_id' => $user->id, 'client_id' => $clientId, 'revoked' => false, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addDays(30)]);
    DB::table('oauth_refresh_tokens')->insert(['id' => Str::random(80), 'access_token_id' => $tokenId, 'revoked' => false, 'expires_at' => now()->addDays(90)]);

    takeoverResetPassword($user);

    $user->refresh();
    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and($wahoo->revoked)->toBe(['access-left'])
        ->and(DB::table('passkeys')->count())->toBe(0)
        ->and(DB::table('device_links')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('oauth_access_tokens')->where('id', $tokenId)->value('revoked'))->toBeTruthy()
        ->and(DB::table('oauth_refresh_tokens')->where('access_token_id', $tokenId)->value('revoked'))->toBeTruthy()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->remember_token)->not->toBe('remember-before')
        ->and(Hash::check('owner-password', (string) $user->password))->toBeTrue();
    expect(Mail::queued(AccountMessage::class)->map(fn (AccountMessage $accountMessage): string => $accountMessage->template)->all())->toBe(['password-changed']);

    takeoverStravaReturns();

    $this->assertGuest();
});

it('keeps the services of an account whose email is confirmed when its password is reset', function (): void {
    $user = User::factory()->create();
    $connectedAccount = ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    takeoverResetPassword($user);

    $this->assertModelExists($connectedAccount);
});

it('keeps every way in for a rider who confirmed an email before and changed it, when the password is reset', function (): void {
    $user = User::factory()->withTwoFactor()->create(['email' => 'ann@example.com']);
    $connectedAccount = ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Ann',
        'credential_id' => 'credential-kept',
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // An admin changed the address, or the rider did before a new email waited for its link, so it waits to be
    // confirmed. The rider was welcomed when they confirmed their first one.
    $user->forceFill(['email' => 'ann@example.org', 'email_verified_at' => null])->save();
    $user->refresh();
    expect($user->email_verified_at)->toBeNull()
        ->and($user->welcomed_at)->not->toBeNull();

    takeoverResetPassword($user);

    $user->refresh();
    expect(Hash::check('owner-password', (string) $user->password))->toBeTrue()
        ->and($user->two_factor_secret)->not->toBeNull()
        ->and(DB::table('passkeys')->where('user_id', $user->id)->count())->toBe(1);
    $this->assertModelExists($connectedAccount);
});

it('keeps every way in for a rider an admin confirmed, whose email an admin then changed, when the password is reset', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $user = User::factory()->unverified()->withTwoFactor()->create(['email' => 'ann@example.com']);
    $connectedAccount = ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user->id), ['name' => $user->name, 'email' => $user->email, 'confirm_email' => '1'])
        ->assertSessionHasNoErrors();
    auth()->logout();
    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user->id), ['name' => $user->name, 'email' => 'ann@example.org'])
        ->assertSessionHasNoErrors();
    auth()->logout();

    takeoverResetPassword($user->refresh());

    $this->assertModelExists($connectedAccount);
    expect($user->refresh()->two_factor_secret)->not->toBeNull();
});
