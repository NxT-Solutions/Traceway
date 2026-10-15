<?php

declare(strict_types=1);

use App\Domain\Courses\Services\CourseServices;
use App\Domain\Routing\Sport;
use App\Models\RouteServiceAccount;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\RecordingCourseService;

uses(RefreshDatabase::class);

/**
 * Sets up Wahoo and Ride with GPS as services that record what they were sent.
 *
 * @return array{wahoo: RecordingCourseService, ride_with_gps: RecordingCourseService}
 */
function fakeRouteServices(): array
{
    $services = [
        'wahoo' => new RecordingCourseService('wahoo'),
        'ride_with_gps' => new RecordingCourseService('ride_with_gps'),
    ];

    app()->instance(CourseServices::class, new CourseServices($services));

    return $services;
}

function connectWahoo(User $user, array $attributes = []): RouteServiceAccount
{
    return RouteServiceAccount::factory()->for($user)->create($attributes);
}

it('sends the rider to the service with a state, and connects them when it comes back', function (): void {
    fakeRouteServices();
    $user = User::factory()->create();

    $redirect = $this->actingAs($user)
        ->get(route('services.connect', ['service' => 'wahoo', 'return' => '/saved']))
        ->assertRedirect();

    $state = session('route_services.state.wahoo');
    expect($state)->toBeString()
        ->and($redirect->headers->get('Location'))->toBe('https://wahoo.test/oauth/authorize?state='.$state);

    $this->get(route('services.callback', ['service' => 'wahoo', 'state' => $state, 'code' => 'abc']))
        ->assertRedirect('/saved');

    $routeServiceAccount = RouteServiceAccount::query()->sole();
    expect($routeServiceAccount->user_id)->toBe($user->id)
        ->and($routeServiceAccount->access_token)->toBe('access-abc')
        ->and($routeServiceAccount->refresh_token)->toBe('refresh-abc')
        ->and(DB::table('route_service_accounts')->value('access_token'))->not->toContain('access-abc');
});

it('refuses a sign-in that did not start here', function (): void {
    fakeRouteServices();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('services.connect', ['service' => 'wahoo']));

    $this->get(route('services.callback', ['service' => 'wahoo', 'state' => 'forged', 'code' => 'abc']))
        ->assertForbidden();

    expect(RouteServiceAccount::query()->count())->toBe(0);
});

it('does not send the rider back to another site after connecting', function (string $return): void {
    fakeRouteServices();

    $this->actingAs(User::factory()->create())
        ->get(route('services.connect', ['service' => 'wahoo', 'return' => $return]));

    $this->get(route('services.callback', ['service' => 'wahoo', 'state' => session('route_services.state.wahoo'), 'code' => 'abc']))
        ->assertRedirect(route('devices.edit'));
})->with([
    'other site' => 'https://elsewhere.test/',
    'protocol relative' => '//elsewhere.test/',
    'backslash' => '/\\elsewhere.test',
]);

it('says the service was not connected when the rider declined there', function (): void {
    fakeRouteServices();

    $this->actingAs(User::factory()->create())->get(route('services.connect', ['service' => 'wahoo']));

    $this->get(route('services.callback', ['service' => 'wahoo', 'state' => session('route_services.state.wahoo'), 'error' => 'access_denied']))
        ->assertRedirect(route('devices.edit'));

    expect(RouteServiceAccount::query()->count())->toBe(0);
});

it('hides a service this Traceway has no credentials for', function (): void {
    app()->instance(CourseServices::class, new CourseServices([]));
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('services.connect', ['service' => 'wahoo']))->assertNotFound();
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
        ->and(RouteServiceAccount::query()->sole()->access_token)->toBe('access-refreshed');
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

    expect(RouteServiceAccount::query()->sole()->needs_reconnect)->toBeTrue()
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
    connectWahoo($user, ['service' => 'ride_with_gps', 'refresh_token' => null, 'expires_at' => null]);
    $route = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'ride_with_gps']))
        ->assertOk()
        ->assertJson(['status' => 'processing', 'url' => null]);
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
    $services = fakeRouteServices();
    $user = User::factory()->create();
    connectWahoo($user, ['access_token' => 'the-token']);
    $route = SavedRoute::factory()->for($user)->create();
    $this->actingAs($user)->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))->assertOk();

    $this->actingAs($user)
        ->delete(route('services.destroy', ['service' => 'wahoo']))
        ->assertRedirect(route('devices.edit'));

    expect($services['wahoo']->revoked)->toBe(['the-token'])
        ->and(RouteServiceAccount::query()->count())->toBe(0)
        ->and(DB::table('sent_routes')->count())->toBe(0);
});

it('withdraws access at each service before the account is deleted', function (): void {
    $services = fakeRouteServices();
    $user = User::factory()->create(['password' => 'password']);
    connectWahoo($user, ['access_token' => 'the-token']);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect();

    expect($services['wahoo']->revoked)->toBe(['the-token'])
        ->and(RouteServiceAccount::query()->count())->toBe(0);
});

it('asks a guest to log in', function (): void {
    fakeRouteServices();
    $route = SavedRoute::factory()->create();

    $this->postJson(route('routes.saved.send', ['savedRoute' => $route->id, 'service' => 'wahoo']))->assertUnauthorized();
    $this->get(route('services.connect', ['service' => 'wahoo']))->assertRedirect(route('login'));
});
