<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Domain\Routing\Sport;
use App\Mail\AccountMessage;
use App\Models\ConnectedAccount;
use App\Models\SavedRoute;
use App\Models\User;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Sets up Wahoo and Ride with GPS as services that record what they were sent. Their keys also turn on the
 * connections a rider makes to reach them.
 *
 * @return array{wahoo: RecordingCourseService, ride_with_gps: RecordingCourseService}
 */
function fakeRouteServices(): array
{
    config([
        'services.wahoo.client_id' => 'wahoo-client',
        'services.wahoo.client_secret' => 'wahoo-secret',
        'services.ride_with_gps.client_id' => 'rwgps-client',
        'services.ride_with_gps.client_secret' => 'rwgps-secret',
    ]);

    $services = [
        'wahoo' => new RecordingCourseService('wahoo'),
        'ride_with_gps' => new RecordingCourseService('ride_with_gps'),
    ];

    app()->instance(CourseServices::class, new CourseServices($services));

    return $services;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function connectWahoo(User $user, array $attributes = []): ConnectedAccount
{
    return ConnectedAccount::factory()->for($user)->create($attributes);
}

/**
 * Has Wahoo answer the next sign-in with the rider's account there and fresh keys.
 */
function routeServicesWahooAnswers(): void
{
    Socialite::fake('wahoo', (new SocialiteUser)
        ->map(['id' => '60462', 'name' => 'Road Rider', 'email' => 'road@wahoo.test'])
        ->setToken('access-abc')
        ->setRefreshToken('refresh-abc')
        ->setExpiresIn(7200)
        ->setApprovedScopes(['user_read', 'email', 'routes_read', 'routes_write']));
}

/**
 * Starts connecting Wahoo from Settings as a rider who just confirmed it is them.
 */
function routeServicesStartWahoo(User $user, ?string $return = null): TestResponse
{
    return test()->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('connections.connect', ['provider' => 'wahoo', 'return' => $return]));
}

it('sends the rider to Wahoo, and connects them when it comes back', function (): void {
    Mail::fake();
    fakeRouteServices();
    routeServicesWahooAnswers();
    $user = User::factory()->create();

    routeServicesStartWahoo($user, '/saved')->assertRedirect('https://socialite.fake/wahoo/authorize');

    $this->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'abc', 'state' => 'from-wahoo']))
        ->assertRedirect('/saved');

    $connectedAccount = ConnectedAccount::query()->sole();
    expect($connectedAccount->user_id)->toBe($user->id)
        ->and($connectedAccount->provider)->toBe('wahoo')
        ->and($connectedAccount->provider_user_id)->toBe('60462')
        ->and($connectedAccount->access_token)->toBe('access-abc')
        ->and($connectedAccount->refresh_token)->toBe('refresh-abc')
        ->and(DB::table('connected_accounts')->value('access_token'))->not->toContain('access-abc')
        ->and(DB::table('connected_accounts')->value('refresh_token'))->not->toContain('refresh-abc')
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Wahoo connected. Send a route to it from Send to device, or log in with it.']);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added');
});

it('keeps the old Send to device link to connect a service working', function (): void {
    fakeRouteServices();

    $this->actingAs(User::factory()->create())
        ->get(route('services.connect', ['service' => 'ride_with_gps', 'return' => '/saved']))
        ->assertRedirect(route('connections.connect', ['provider' => 'ride_with_gps', 'return' => '/saved']));
});

it('refuses an answer from Wahoo for a connection that did not start here', function (): void {
    Mail::fake();
    fakeRouteServices();
    routeServicesWahooAnswers();

    $this->actingAs(User::factory()->create())
        ->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'abc', 'state' => 'forged']))
        ->assertRedirect(route('connections.edit'));

    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'error', 'message' => 'That took too long, or it started in another tab. Try again.']);
    Mail::assertNothingQueued();
});

it('does not send the rider back to another site after connecting', function (string $return): void {
    Mail::fake();
    fakeRouteServices();
    routeServicesWahooAnswers();

    routeServicesStartWahoo(User::factory()->create(), $return);

    $this->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'abc', 'state' => 'from-wahoo']))
        ->assertRedirect(route('connections.edit'));
})->with([
    'other site' => 'https://elsewhere.test/',
    'protocol relative' => '//elsewhere.test/',
    'backslash' => '/\\elsewhere.test',
]);

it('says the service was not connected when the rider declined there', function (): void {
    Mail::fake();
    fakeRouteServices();
    routeServicesWahooAnswers();

    routeServicesStartWahoo(User::factory()->create(), '/saved');

    $this->get(route('services.callback', ['provider' => 'wahoo', 'error' => 'access_denied', 'state' => 'from-wahoo']))
        ->assertRedirect('/saved');

    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'error', 'message' => 'Wahoo did not connect, because it was cancelled there.']);
    Mail::assertNothingQueued();
});

it('brings the rider back with a message when the service turns the sign-in down', function (): void {
    Mail::fake();
    fakeRouteServices();
    Socialite::fake('wahoo', fn (): never => throw new ClientException(
        'The code was used already.',
        new GuzzleRequest('POST', 'https://api.wahooligan.com/oauth/token'),
        new GuzzleResponse(400, [], '{"error":"invalid_grant"}'),
    ));

    routeServicesStartWahoo(User::factory()->create(), '/saved');

    $this->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'used', 'state' => 'from-wahoo']))
        ->assertRedirect('/saved');

    expect(ConnectedAccount::query()->count())->toBe(0)
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'error', 'message' => 'Wahoo turned the request down. Try again. If it keeps happening, disconnect Wahoo in Settings, under Connections, and connect it again.']);
    Mail::assertNothingQueued();
});

it('hides a service this Traceway has no credentials for', function (): void {
    app()->instance(CourseServices::class, new CourseServices([]));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('connections.connect', ['provider' => 'wahoo']))
        ->assertNotFound();
    $this->actingAs($user)->get(route('devices.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('services', []));
});

it('lists the services on the devices page with what is connected', function (): void {
    fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user, ['needs_reconnect' => true]);

    $this->actingAs($user)->get(route('devices.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('services.0.service', 'wahoo')
            ->where('services.0.connected', expected: true)
            ->where('services.0.needs_reconnect', expected: true)
            ->where('services.1.service', 'ride_with_gps')
            ->where('services.1.connected', expected: false));
});

it('sends a saved route to Wahoo and updates it there the second time', function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user);
    $route = SavedRoute::factory()->for($user)->create(['name' => 'A very long morning loop', 'sport' => 'trail_run']);

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertOk()
        ->assertJson(['status' => 'sent', 'service' => 'wahoo', 'url' => 'https://wahoo.test/routes/101']);

    $upload = $services['wahoo']->published[0]['upload'];
    expect($upload->title)->toBe('A very long morning loop')
        ->and(mb_strlen($upload->deviceName))->toBeLessThanOrEqual(15)
        ->and($upload->sport)->toBe(Sport::TrailRun)
        ->and($upload->externalId)->toBe($route->id)
        ->and($upload->fit)->not->toBe('')
        ->and($upload->gpx)->toContain('<gpx')
        ->and($services['wahoo']->published[0]['previous'])->toBeNull();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertOk();

    expect($services['wahoo']->published[1]['previous'])->toBe('101')
        ->and(DB::table('sent_routes')->count())->toBe(1)
        ->and(DB::table('sent_routes')->value('external_id'))->toBe('102');

    $this->actingAs($user)->get(route('routes.library'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('routes.0.sent_to', ['wahoo'])
            ->where('routes.0.gpx_url', fn (string $url): bool => str_contains($url, '/share/'.$route->id.'/gpx')));
});

it('refreshes a token that ran out before sending', function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user, ['expires_at' => now()->subMinute()]);
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertOk();

    expect($services['wahoo']->refreshed)->toBe(1)
        ->and($services['wahoo']->published[0]['tokens']->accessToken)->toBe('access-refreshed')
        ->and(ConnectedAccount::query()->sole()->access_token)->toBe('access-refreshed');
});

it('asks the rider to connect first', function (): void {
    fakeRouteServices();
    $user = User::factory()->create();
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertConflict()
        ->assertJson(['message' => 'Connect Wahoo first.']);
});

it("does not send another rider's route", function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user);
    $foreign = SavedRoute::factory()->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $foreign->id, 'service' => 'wahoo']))
        ->assertNotFound();

    expect($services['wahoo']->published)->toBe([]);
});

it('marks the service for reconnecting when it no longer accepts Traceway', function (): void {
    $services = fakeRouteServices();
    $services['wahoo']->failure = 'refused';
    $user = User::factory()->create();
    connectWahoo($user);
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertConflict();

    expect(ConnectedAccount::query()->sole()->needs_reconnect)->toBeTrue()
        ->and(DB::table('sent_routes')->count())->toBe(0);
});

it('says when the service is down', function (): void {
    $services = fakeRouteServices();
    $services['wahoo']->failure = 'unavailable';
    $user = User::factory()->create();
    connectWahoo($user);
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertStatus(502);
});

it('tells the rider Ride with GPS is still reading a slow upload', function (): void {
    $services = fakeRouteServices();
    $services['ride_with_gps']->ready = false;
    $user = User::factory()->create();
    connectWahoo($user, ['provider' => 'ride_with_gps', 'refresh_token' => null, 'expires_at' => null]);
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'ride_with_gps']))
        ->assertOk()
        ->assertJson(['status' => 'processing', 'url' => null]);

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'ride_with_gps']))
        ->assertOk();

    // The slow upload is kept by its handle, so the next send replaces it instead of adding a copy.
    expect($services['ride_with_gps']->published[1]['previous'])->toBe('task:101');
});

it('sends a multisport plan one land leg at a time', function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user);
    $route = SavedRoute::factory()->for($user)->create([
        'name' => 'Brick',
        'legs' => [
            ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11]]],
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 40, 'coordinates' => [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]],
        ],
    ]);

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']), ['leg' => 1])
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']), ['leg' => 2])
        ->assertOk();

    $upload = $services['wahoo']->published[0]['upload'];
    expect($upload->title)->toBe('Brick, leg 2')
        ->and($upload->sport)->toBe(Sport::Road)
        ->and($upload->distanceMetres)->toBe(20_000)
        ->and($upload->externalId)->toBe($route->id.'-2')
        ->and(DB::table('sent_routes')->value('leg'))->toBe(2);
});

it('withdraws access and forgets what was sent when the rider disconnects', function (): void {
    Mail::fake();
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user, ['access_token' => 'the-token']);
    $route = SavedRoute::factory()->for($user)->create();
    $this->actingAs($user)->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))->assertOk();

    $this->actingAs($user)
        ->delete(route('connections.destroy', ['provider' => 'wahoo']))
        ->assertRedirect(route('connections.edit'));

    expect($services['wahoo']->revoked)->toBe(['the-token'])
        ->and(ConnectedAccount::query()->count())->toBe(0)
        ->and(DB::table('sent_routes')->count())->toBe(0)
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Wahoo disconnected.']);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-removed');
});

it("refreshes a token that ran out before withdrawing it, so the service does not keep Traceway's access", function (): void {
    Mail::fake();
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user, ['access_token' => 'stale', 'expires_at' => now()->subHour()]);

    $this->actingAs($user)->delete(route('connections.destroy', ['provider' => 'wahoo']))->assertRedirect();

    expect($services['wahoo']->refreshed)->toBe(1)
        ->and($services['wahoo']->revoked)->toBe(['access-refreshed']);
});

it('withdraws access at each service before the account is deleted', function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create(['password' => 'password']);
    connectWahoo($user, ['access_token' => 'the-token']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect();

    expect($services['wahoo']->revoked)->toBe(['the-token'])
        ->and(ConnectedAccount::query()->count())->toBe(0);
});

it('asks a guest to log in', function (): void {
    fakeRouteServices();
    $route = SavedRoute::factory()->create();

    $this->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))->assertUnauthorized();
    $this->get(route('connections.connect', ['provider' => 'wahoo']))->assertRedirect(route('login'));
    $this->delete(route('connections.destroy', ['provider' => 'wahoo']))->assertRedirect(route('login'));
});

it('mails the rider when a Wahoo connection from before gets its number, and keeps when it was made and what was sent', function (): void {
    Mail::fake();
    $services = fakeRouteServices();
    routeServicesWahooAnswers();
    $user = User::factory()->create();
    $connectedAccount = connectWahoo($user, ['provider_user_id' => null, 'access_token' => 'access-old', 'connected_at' => now()->subYear()]);
    $route = SavedRoute::factory()->for($user)->create();
    DB::table('sent_routes')->insert(['user_id' => $user->id, 'saved_route_id' => $route->id, 'leg' => 0, 'service' => 'wahoo', 'external_id' => '7', 'sent_at' => now()]);
    $connectedAt = $connectedAccount->connected_at->getTimestamp();

    routeServicesStartWahoo($user, '/saved');
    $this->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'abc', 'state' => 'from-wahoo']))
        ->assertRedirect('/saved');

    $connectedAccount->refresh();
    expect($connectedAccount->provider_user_id)->toBe('60462')
        ->and($connectedAccount->connected_at->getTimestamp())->toBe($connectedAt)
        ->and($connectedAccount->access_token)->toBe('access-abc')
        ->and(DB::table('sent_routes')->count())->toBe(1)
        ->and($services['wahoo']->superseded)->toBe(['access-old'])
        ->and(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Wahoo connected. Send a route to it from Send to device, or log in with it.']);
    Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'connection-added'
        && $accountMessage->hasTo($user->email)
        && $accountMessage->values['service'] === 'Wahoo');
});

it('lets go of the old keys when the rider connects the same account again with new ones', function (string $stored, array $superseded): void {
    Mail::fake();
    $services = fakeRouteServices();
    routeServicesWahooAnswers();
    $user = User::factory()->create();
    connectWahoo($user, ['provider_user_id' => '60462', 'access_token' => $stored]);

    routeServicesStartWahoo($user);
    $this->get(route('services.callback', ['provider' => 'wahoo', 'code' => 'abc', 'state' => 'from-wahoo']))
        ->assertRedirect(route('connections.edit'));

    expect($services['wahoo']->superseded)->toBe($superseded)
        ->and($services['wahoo']->revoked)->toBe([])
        ->and(ConnectedAccount::query()->sole()->access_token)->toBe('access-abc');
    Mail::assertNothingQueued();
})->with([
    'new keys' => ['access-old', ['access-old']],
    'the same keys' => ['access-abc', []],
]);

it('lets go of the old Ride with GPS key when a log in brings a new one, and logs in even if that fails', function (?string $failure): void {
    $services = fakeRouteServices();
    $services['ride_with_gps']->failure = $failure;
    $user = User::factory()->create();
    connectWahoo($user, ['provider' => 'ride_with_gps', 'provider_user_id' => '4711', 'access_token' => 'rwgps-old', 'refresh_token' => null, 'expires_at' => null]);
    Socialite::fake('ride_with_gps', (new SocialiteUser)
        ->map(['id' => '4711', 'name' => 'Road Rider', 'email' => 'road@rwgps.test'])
        ->setToken('rwgps-new'));

    $this->get(route('connections.sign-in', ['provider' => 'ride_with_gps']));
    $this->get(route('connections.callback', ['provider' => 'ride_with_gps', 'code' => 'from-rwgps', 'state' => 'state']))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($services['ride_with_gps']->superseded)->toBe(['rwgps-old'])
        ->and($services['ride_with_gps']->revoked)->toBe([])
        ->and(ConnectedAccount::query()->sole()->access_token)->toBe('rwgps-new');
})->with([
    'it lets go' => [null],
    'it is down' => ['unavailable'],
    'the old key stopped working' => ['refused'],
]);

it('tells the devices page why the rider cannot disconnect their only way to log in, as the connections page does', function (): void {
    fakeRouteServices();
    $user = User::factory()->create(['password' => null]);
    connectWahoo($user);

    $this->actingAs($user)->get(route('devices.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('disconnectBlocked.wahoo', 'Add a password or a passkey first, so you can still log in.')
            ->where('disconnectBlocked.ride_with_gps', expected: null));
    $this->actingAs($user)->get(route('connections.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('connected.0.slug', 'wahoo')
            ->where('connected.0.disconnect_blocked', 'Add a password or a passkey first, so you can still log in.'));
});

it('lets the devices page offer Disconnect while the rider can still log in another way', function (bool $password, array $others): void {
    fakeRouteServices();
    $user = User::factory()->create(['password' => $password ? 'password' : null]);
    connectWahoo($user);

    foreach ($others as $other) {
        connectWahoo($user, ['provider' => $other]);
    }

    $this->actingAs($user)->get(route('devices.edit'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('disconnectBlocked.wahoo', expected: null)
            ->where('disconnectBlocked.ride_with_gps', expected: null));
})->with([
    'a password' => [true, []],
    'another service' => [false, ['ride_with_gps']],
]);
