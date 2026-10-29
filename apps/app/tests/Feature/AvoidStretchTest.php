<?php

declare(strict_types=1);

use App\Domain\Routing\Distance;
use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Models\AvoidedStretch;
use App\Models\User;
use App\Routes\CachedMapServerPace;
use App\Routes\TooManyAvoidedStretches;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Saloon\Config;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Config::sleepUsing(sleepHandler: null);
});

/**
 * The router answers each lookup in turn with the next response.
 */
function edgeConnector(MockResponse ...$mockResponses): MockClient
{
    $mock = new MockClient(array_values($mockResponses));
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    app()->instance(ValhallaConnector::class, $connector);

    return $mock;
}

/**
 * @param  list<array{0: float, 1: float}>  $shape
 */
function edgeUnder(array $shape, ?string $name = 'Bosweg', int $wayId = 4242): MockResponse
{
    return MockResponse::make([[
        'edges' => [[
            'correlated_lat' => 51.05,
            'correlated_lon' => 3.722,
            'edge_info' => [
                'way_id' => $wayId,
                'names' => $name === null ? [] : [$name],
                'shape' => Polyline::encode($shape),
            ],
        ]],
    ]]);
}

it('keeps the way under the tap for the rider, by the middle of the way', function (): void {
    $user = User::factory()->create();
    $mockClient = edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $response = $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.0501, 'longitude' => 3.7201, 'sport' => 'gravel'])
        ->assertCreated()
        ->assertJsonPath('name', 'Bosweg')
        ->assertJsonPath('sport', 'gravel')
        ->assertJsonPath('surface', 'mixed')
        ->assertJsonPath('shape', [[3.72, 51.05], [3.724, 51.05]]);

    $avoidedStretch = AvoidedStretch::query()->sole();

    // The tap landed near the west junction; the point kept is half way along.
    expect($response->json('latitude'))->toEqualWithDelta(51.05, 0.000001)
        ->and($response->json('longitude'))->toEqualWithDelta(3.722, 0.000001)
        ->and($avoidedStretch->user_id)->toBe($user->id)
        ->and($avoidedStretch->way_id)->toBe(4242)
        ->and($avoidedStretch->sport)->toBe('gravel')
        ->and($mockClient->getLastPendingRequest()?->body()?->all())->toMatchArray([
            'verbose' => true,
            'costing' => 'bicycle',
            'costing_options' => ['bicycle' => ['bicycle_type' => 'Cross', 'avoid_bad_surfaces' => 0.35]],
        ]);
});

it('keeps a way without a name as unnamed', function (): void {
    edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]], name: null));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertCreated()
        ->assertJsonPath('name', null);
});

it('keeps only the part of a long way around the tap', function (): void {
    edgeConnector(edgeUnder([[3.6, 51.05], [3.722, 51.05], [3.85, 51.05]]));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertCreated();

    $shape = AvoidedStretch::query()->sole()->shape;

    expect(Distance::along($shape))->toEqualWithDelta(2_000.0, 5.0);
});

it('keeps the same way once', function (): void {
    $user = User::factory()->create();
    edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]), edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $first = $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->json('id');

    $this->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.7221, 'sport' => 'road'])
        ->assertCreated()
        ->assertJsonPath('id', $first);

    expect(AvoidedStretch::query()->count())->toBe(1);
});

it('says so when no road is close enough', function (): void {
    edgeConnector(MockResponse::make([['edges' => null]]));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No road or path is close enough there. Tap right on the road.');

    expect(AvoidedStretch::query()->count())->toBe(0);
});

it('says the map is busy when the router does not answer', function (): void {
    Config::sleepUsing(sleepHandler: static fn (): null => null);
    edgeConnector(MockResponse::make([], 503), MockResponse::make([], 503));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertServiceUnavailable();
});

it('says the map is busy, and asks and reports nothing, while Traceway rests from the map server', function (): void {
    Exceptions::fake();
    $mock = new MockClient([edgeUnder([[3.72, 51.05], [3.724, 51.05]])]);
    $cachedMapServerPace = new CachedMapServerPace(resolve(Repository::class));
    $cachedMapServerPace->rest();

    $connector = new ValhallaConnector('https://valhalla.test', $cachedMapServerPace);
    $connector->withMockClient($mock);

    app()->instance(ValhallaConnector::class, $connector);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The map is busy. Try again in a moment.');

    $mock->assertNothingSent();
    Exceptions::assertNothingReported();
    expect(AvoidedStretch::query()->count())->toBe(0);
});

it('stops at the most roads a rider can avoid', function (): void {
    $user = User::factory()->create();
    AvoidedStretch::factory()->count(TooManyAvoidedStretches::MOST)->for($user)->create([
        'latitude' => 50.5,
        'longitude' => 4.5,
        'shape' => [[4.499, 50.5], [4.501, 50.5]],
        'way_id' => 1,
    ]);
    edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You already avoid 200 roads. Remove one you no longer need first.');

    expect(AvoidedStretch::query()->count())->toBe(TooManyAvoidedStretches::MOST);
});

it('still answers with a road the rider already avoids at the most', function (): void {
    $user = User::factory()->create();
    $kept = AvoidedStretch::factory()->for($user)->create();
    AvoidedStretch::factory()->count(TooManyAvoidedStretches::MOST - 1)->for($user)->create([
        'latitude' => 50.5,
        'longitude' => 4.5,
        'shape' => [[4.499, 50.5], [4.501, 50.5]],
        'way_id' => 1,
    ]);
    edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'gravel'])
        ->assertCreated()
        ->assertJsonPath('id', $kept->id);
});

it('keeps a long way once when it is tapped in two places', function (): void {
    $user = User::factory()->create();
    $long = [[3.6, 51.05], [3.722, 51.05], [3.85, 51.05]];
    edgeConnector(edgeUnder($long), edgeUnder($long));

    $first = $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->json('id');

    // 500 m east: the same way, cut around another tap.
    $this->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.7291, 'sport' => 'road'])
        ->assertJsonPath('id', $first);

    expect(AvoidedStretch::query()->count())->toBe(1);
});

it('lets a bike plan skip a road first avoided on foot, once it is avoided on a bike', function (): void {
    $user = User::factory()->create();
    edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]), edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'hike'])
        ->assertJsonPath('sport', 'hike');
    $this->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'gravel']);

    expect(AvoidedStretch::query()->sole()->sport)->toBe('gravel');
});

it('asks for a point and a sport', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.avoided.store'), ['latitude' => 120, 'sport' => 'unicycle'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude', 'sport']);
});

it('keeps nothing for a guest', function (): void {
    $this->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertUnauthorized();
});

it('stops avoiding a road', function (): void {
    $stretch = AvoidedStretch::factory()->create();

    $this->actingAs($stretch->user()->sole())
        ->deleteJson(route('routes.avoided.destroy', $stretch->id))
        ->assertNoContent();

    expect(AvoidedStretch::query()->count())->toBe(0);
});

it("leaves another rider's road alone", function (): void {
    $stretch = AvoidedStretch::factory()->create();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('routes.avoided.destroy', $stretch->id))
        ->assertForbidden();

    expect(AvoidedStretch::query()->count())->toBe(1);
});

it('says when the road is already gone', function (): void {
    $this->actingAs(User::factory()->create())
        ->deleteJson(route('routes.avoided.destroy', '0199f2a4-6c1b-7d3e-9a8f-2b4c6d8e0f12'))
        ->assertNotFound();
});

it("draws the rider's own avoided roads on the planner", function (): void {
    $user = User::factory()->create();
    AvoidedStretch::factory()->for($user)->create(['name' => 'Bosweg']);
    AvoidedStretch::factory()->create(['name' => 'Somebody else']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('avoided', 1)
            ->where('avoided.0.name', 'Bosweg')
            ->where('avoided.0.shape', [[3.72, 51.05], [3.724, 51.05]]));
});
