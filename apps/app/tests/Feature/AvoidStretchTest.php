<?php

declare(strict_types=1);

use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Models\AvoidedStretch;
use App\Models\User;
use App\Routes\TooManyAvoidedStretches;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('keeps the way under the tap for the rider', function (): void {
    $user = User::factory()->create();
    $mock = edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.0501, 'longitude' => 3.7221, 'sport' => 'gravel'])
        ->assertCreated()
        ->assertJsonPath('name', 'Bosweg')
        ->assertJsonPath('latitude', 51.05)
        ->assertJsonPath('longitude', 3.722)
        ->assertJsonPath('shape', [[3.72, 51.05], [3.724, 51.05]]);

    $stretch = AvoidedStretch::query()->sole();

    expect($stretch->user_id)->toBe($user->id)
        ->and($stretch->way_id)->toBe(4242)
        ->and($mock->getLastPendingRequest()?->body()?->all())->toMatchArray([
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

    expect(App\Domain\Routing\Distance::along($shape))->toEqualWithDelta(2_000.0, 5.0);
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

it('stops at the most roads a rider can avoid', function (): void {
    $user = User::factory()->create();
    AvoidedStretch::factory()->count(TooManyAvoidedStretches::MOST)->for($user)->create();
    $mock = edgeConnector(edgeUnder([[3.72, 51.05], [3.724, 51.05]]));

    $this->actingAs($user)
        ->postJson(route('routes.avoided.store'), ['latitude' => 51.05, 'longitude' => 3.722, 'sport' => 'road'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You already avoid 200 roads. Remove one you no longer need first.');

    $mock->assertNothingSent();
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

it('draws the rider\'s own avoided roads on the planner', function (): void {
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
