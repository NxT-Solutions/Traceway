<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

function connectFromSettingsGoogleAnswers(string $id = '1001'): void
{
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    Socialite::fake('google', (new SocialiteUser)
        ->setRaw(['sub' => $id, 'email' => 'ann@gmail.com', 'email_verified' => true])
        ->map(['id' => $id, 'name' => 'Ann Rider', 'email' => 'ann@gmail.com'])
        ->setApprovedScopes(['openid', 'email', 'profile']));
}

/**
 * Starts connecting the service from Settings as a rider who just confirmed it is them.
 */
function connectFromSettingsStart(User $user, string $provider = 'google', ?string $return = null): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('connections.connect', ['provider' => $provider, 'return' => $return]));
}

function connectFromSettingsReturns(string $provider = 'google'): TestResponse
{
    return test()->get(route('connections.callback', ['provider' => $provider, 'code' => 'from-service', 'state' => 'state']));
}

it('asks the rider to confirm it is them before connecting a service', function (): void {
    connectFromSettingsGoogleAnswers();

    $this->actingAs(User::factory()->create())
        ->get(route('connections.connect', ['provider' => 'google']))
        ->assertRedirect(route('password.confirm'));

    expect(session('connections.flow'))->toBeNull();
});

it('connects the account to the rider who started, and brings them back where they were', function (): void {
    Mail::fake();
    connectFromSettingsGoogleAnswers();
    $user = User::factory()->create();

    connectFromSettingsStart($user, 'google', '/saved')->assertRedirect('https://socialite.fake/google/authorize');
    connectFromSettingsReturns()
        ->assertRedirect('/saved')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google connected. You can log in with it now.']);

    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider)->toBe('google')
        ->and($connectedAccount->provider_user_id)->toBe('1001')
        ->and($connectedAccount->account_label)->toBe('ann@gmail.com');
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added'
        && $accountMessage->hasTo($user->email)
        && $accountMessage->values['url'] === route('connections.edit'));
});

it('does not connect an account that is already connected to another rider', function (): void {
    Mail::fake();
    connectFromSettingsGoogleAnswers('1001');
    $owner = ConnectedAccount::factory()->signInOnly('google')->create(['provider_user_id' => '1001']);
    $user = User::factory()->create();

    connectFromSettingsStart($user);
    connectFromSettingsReturns()
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This Google account is already connected to another Traceway account.']);

    expect(ConnectedAccount::query()->sole()->is($owner))->toBeTrue()
        ->and(ConnectedAccount::query()->sole()->user_id)->toBe($owner->user_id);
    Mail::assertNothingQueued();
});

it('puts a different account at the same service in place of the old one, and hands back the old keys', function (): void {
    Mail::fake();
    config(['services.wahoo.client_id' => 'wahoo-client', 'services.wahoo.client_secret' => 'wahoo-secret']);
    $wahoo = new RecordingCourseService('wahoo');
    app()->instance(CourseServices::class, new CourseServices(['wahoo' => $wahoo]));
    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => 'road@wahoo.test'])
        ->setToken('access-new')
        ->setRefreshToken('refresh-new')
        ->setExpiresIn(7200)
        ->setApprovedScopes(['user_read', 'email', 'routes_read', 'routes_write']));
    $user = User::factory()->create();
    ConnectedAccount::factory()->for($user)->create(['provider_user_id' => '111', 'access_token' => 'access-old']);
    $savedRoute = SavedRoute::factory()->for($user)->create();
    DB::table('sent_routes')->insert([
        ['user_id' => $user->id, 'saved_route_id' => $savedRoute->id, 'leg' => 0, 'service' => 'wahoo', 'external_id' => '7', 'sent_at' => now()],
        ['user_id' => $user->id, 'saved_route_id' => $savedRoute->id, 'leg' => 0, 'service' => 'ride_with_gps', 'external_id' => '8', 'sent_at' => now()],
    ]);

    connectFromSettingsStart($user, 'wahoo');
    connectFromSettingsReturns('wahoo')
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Wahoo connected. Send a route to it from Send to device, or log in with it.']);

    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->provider_user_id)->toBe('60462')
        ->and($connectedAccount->access_token)->toBe('access-new')
        ->and($wahoo->revoked)->toBe(['access-old'])
        ->and(DB::table('sent_routes')->pluck('service')->all())->toBe(['ride_with_gps']);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added');
});

it('refreshes the same account without an email, since it adds no new way to log in', function (): void {
    Mail::fake();
    connectFromSettingsGoogleAnswers('1001');
    $user = User::factory()->create();
    $connectedAt = now()->subWeek()->startOfSecond();
    ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001', 'account_label' => 'old@gmail.com', 'connected_at' => $connectedAt]);

    connectFromSettingsStart($user);
    connectFromSettingsReturns()
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Google is connected again.']);

    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->account_label)->toBe('ann@gmail.com')
        ->and($connectedAccount->connected_at->equalTo($connectedAt))->toBeTrue();
    Mail::assertNothingQueued();
});

it('refuses an answer for a connection someone else started in this browser', function (): void {
    Mail::fake();
    connectFromSettingsGoogleAnswers();
    connectFromSettingsStart(User::factory()->create());

    $this->actingAs(User::factory()->create());
    connectFromSettingsReturns()
        ->assertRedirect(route('connections.edit'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'That took too long, or it started in another tab. Try again.']);

    expect(ConnectedAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('ignores a return address that is not one path on this site', function (): void {
    connectFromSettingsGoogleAnswers();
    $user = User::factory()->create();

    test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('connections.connect', ['provider' => 'google']).'?return[]=/saved')
        ->assertRedirect('https://socialite.fake/google/authorize');

    expect(session('connections.flow'))->toMatchArray(['intent' => 'connect', 'return' => null]);

    $this->get('/settings/services/wahoo/connect?return[]=/saved')
        ->assertRedirect(route('connections.connect', ['provider' => 'wahoo']));
});

it('waits until the rider confirms their email before a service is connected or disconnected', function (): void {
    Mail::fake();
    connectFromSettingsGoogleAnswers();
    $user = User::factory()->unverified()->create();
    $connectedAccount = ConnectedAccount::factory()->for($user)->signInOnly('google')->create(['provider_user_id' => '1001']);

    connectFromSettingsStart($user)->assertRedirect(route('verification.notice'));
    expect(session('connections.flow'))->toBeNull();

    $this->delete(route('connections.destroy', ['provider' => 'google']))->assertRedirect(route('verification.notice'));

    $this->assertModelExists($connectedAccount);
    Mail::assertNothingQueued();
});
