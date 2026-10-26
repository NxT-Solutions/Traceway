<?php

declare(strict_types=1);

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Models\AvoidedStretch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A router that joins the points it is given in a straight line and
 * remembers each plan.
 */
function straightEngine(): object
{
    return new class implements RoutingEngine
    {
        /** @var list<RouteQuery> */
        public array $asked = [];

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new RuntimeException('Not used.');
        }

        /**
         * @return list<GeneratedRoute>
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            throw new RuntimeException('Not used.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->asked[] = $routeQuery;

            return new GeneratedRoute($routeQuery, $waypoints, 1_000, 0);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            $this->asked[] = $routeQuery;

            return new GeneratedRoute($routeQuery, [$from, $to], 1_000, 0);
        }
    };
}

/** A square loop near Ghent: the line, and the points it passes. */
const SQUARE_LINE = [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05], [3.74, 51.065], [3.74, 51.08], [3.72, 51.08], [3.70, 51.08], [3.70, 51.065], [3.70, 51.05]];
const SQUARE_POINTS = [[3.70, 51.05], [3.74, 51.05], [3.74, 51.08], [3.70, 51.08], [3.70, 51.05]];

beforeEach(function (): void {
    $this->engine = straightEngine();
    $this->app->instance(RoutingEngine::class, $this->engine);
});

it('moves one point of a loop and keeps the rest of it', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('routes.reshape'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'distance_metres' => 20_000,
            'mode' => 'loop',
            'line' => SQUARE_LINE,
            'waypoints' => SQUARE_POINTS,
            'change' => ['kind' => 'move', 'index' => 2, 'latitude' => 51.085, 'longitude' => 3.75],
        ])
        ->assertOk()
        ->assertJsonPath('changed_legs', [1, 2])
        ->assertJsonPath('waypoints.2', [3.75, 51.085])
        ->assertJsonPath('coordinates.0', [3.70, 51.05])
        ->assertJsonStructure(['distance_metres', 'reverse_share', 'crosses_avoided']);

    // The corners of the legs not touched are still there.
    expect($response->json('coordinates'))->toContain([3.74, 51.05])
        ->toContain([3.70, 51.08])
        ->and($this->engine->asked)->toHaveCount(1);
});

it('moves a built point and finds the way home again', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.reshape'), [
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'mode' => 'build',
            'finish' => true,
            'line' => SQUARE_LINE,
            'waypoints' => SQUARE_POINTS,
            'change' => ['kind' => 'move', 'index' => 3, 'latitude' => 51.081, 'longitude' => 3.699],
        ])
        ->assertOk()
        ->assertJsonCount(3, 'legs_metres')
        ->assertJsonPath('finish_coordinates.0', [3.699, 51.081])
        ->assertJsonStructure(['user_coordinates', 'finish_coordinates', 'crosses_avoided']);

    expect($this->engine->asked)->toHaveCount(2);
});

it("skips the rider's avoided roads on the legs it plans again", function (): void {
    $user = User::factory()->create();
    AvoidedStretch::factory()->for($user)->create(['latitude' => 51.06, 'longitude' => 3.72, 'shape' => [[3.719, 51.06], [3.721, 51.06]]]);

    $this->actingAs($user)
        ->postJson(route('routes.reshape'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'mode' => 'loop',
            'line' => SQUARE_LINE,
            'waypoints' => SQUARE_POINTS,
            'change' => ['kind' => 'reroute', 'legs' => [0]],
        ])
        ->assertOk();

    expect($this->engine->asked[0]->avoid)->toHaveCount(1);
});

it('refuses a change that does not fit the route', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.reshape'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'mode' => 'loop',
            'line' => SQUARE_LINE,
            'waypoints' => SQUARE_POINTS,
            'change' => ['kind' => 'reroute', 'legs' => [9]],
        ])
        ->assertUnprocessable();
});

it('asks for the route, its points, and the change', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.reshape'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'mode' => 'loop',
            'line' => [[3.7, 51.05]],
            'waypoints' => array_fill(0, 15, [3.7, 51.05]),
            'change' => ['kind' => 'move'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['line', 'waypoints', 'change.index', 'change.latitude', 'change.longitude']);
});

it('changes nothing for a guest', function (): void {
    $this->postJson(route('routes.reshape'), [])->assertUnauthorized();
});

it('says so when the router cannot join the changed legs', function (): void {
    $this->app->instance(RoutingEngine::class, new class implements RoutingEngine
    {
        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new RuntimeException('Down.');
        }

        /**
         * @return list<GeneratedRoute>
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            throw new RuntimeException('Down.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            throw new RuntimeException('Down.');
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            throw new RuntimeException('Down.');
        }
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.reshape'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'mode' => 'loop',
            'line' => SQUARE_LINE,
            'waypoints' => SQUARE_POINTS,
            'change' => ['kind' => 'move', 'index' => 1, 'latitude' => 51.05, 'longitude' => 3.745],
        ])
        ->assertStatus(502);
});
