<?php

declare(strict_types=1);

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function multisportEngine(bool $fails = false): RoutingEngine
{
    return new readonly class($fails) implements RoutingEngine
    {
        public function __construct(private bool $fails) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->candidates($routeQuery)[0];
        }

        /**
         * @return list<GeneratedRoute>
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            throw_if($this->fails, RuntimeException::class, 'Valhalla is down.');

            $start = [$routeQuery->startLongitude, $routeQuery->startLatitude];

            return [new GeneratedRoute($routeQuery, [$start, [3.8, 51.1], $start], $routeQuery->distanceMetres, 30)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, $waypoints, 6_000, 12);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, [$from, $to], 1_000, 0);
        }
    };
}

it('draws a triathlon that loops from the transition', function (): void {
    $this->app->instance(RoutingEngine::class, multisportEngine());

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.multisport'), [
            'layout' => 'loops',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'swim' => ['coordinates' => [[3.71, 51.05], [3.71, 51.06]]],
            'legs' => [
                ['sport' => 'road', 'surface' => 'paved', 'elevation' => 'minimize', 'roads' => 'quiet', 'distance_metres' => 40_000],
                ['sport' => 'run', 'surface' => 'mixed', 'elevation' => 'neutral', 'distance_metres' => 10_000],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('layout', 'loops')
        ->assertJsonCount(3, 'legs')
        ->assertJsonPath('legs.0.discipline', 'swim')
        ->assertJsonPath('legs.0.distance_metres', 1_112)
        ->assertJsonPath('legs.1.discipline', 'road')
        ->assertJsonPath('legs.1.coordinates.0', [3.72, 51.05])
        ->assertJsonPath('legs.2.discipline', 'run')
        ->assertJsonPath('legs.2.distance_metres', 10_000)
        ->assertJsonPath('distance_metres', 51_112)
        ->assertJsonPath('ascent_metres', 60);
});

it('chains a duathlon from finish to finish', function (): void {
    $this->app->instance(RoutingEngine::class, multisportEngine());

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.multisport'), [
            'layout' => 'chain',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [
                ['sport' => 'run', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 5_000, 'finish_latitude' => 51.06, 'finish_longitude' => 3.75],
                ['sport' => 'road', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 30_000, 'finish_latitude' => 51.2, 'finish_longitude' => 3.9, 'stretch' => false],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('layout', 'chain')
        ->assertJsonCount(2, 'legs')
        ->assertJsonPath('legs.0.coordinates', [[3.72, 51.05], [3.75, 51.06]])
        ->assertJsonPath('legs.1.coordinates', [[3.75, 51.06], [3.9, 51.2]])
        ->assertJsonPath('distance_metres', 12_000);
});

it('asks for a finish on every chained leg', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.multisport'), [
            'layout' => 'chain',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [
                ['sport' => 'run', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 5_000],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['legs.0.finish_latitude', 'legs.0.finish_longitude']);
});

const RUN_LEG = ['sport' => 'run', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 5_000];

it('refuses a plan with no legs, too many legs, or a long swim', function (array $payload, string $error): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.multisport'), [
            'layout' => 'loops',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [RUN_LEG],
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'no legs' => [['legs' => []], 'legs'],
    'five legs' => [['legs' => array_fill(0, 5, RUN_LEG)], 'legs'],
    'long swim' => [['swim' => ['coordinates' => array_fill(0, 21, [3.71, 51.05])]], 'swim.coordinates'],
    'swim off the map' => [['swim' => ['coordinates' => [[3.71, 91.0]]]], 'swim.coordinates.0.1'],
    'unknown layout' => [['layout' => 'relay'], 'layout'],
    'unknown sport' => [['legs' => [[...RUN_LEG, 'sport' => 'rowing']]], 'legs.0.sport'],
    'short leg' => [['legs' => [[...RUN_LEG, 'distance_metres' => 500]]], 'legs.0.distance_metres'],
]);

it('says so when a leg does not come back', function (): void {
    $this->app->instance(RoutingEngine::class, multisportEngine(fails: true));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.multisport'), [
            'layout' => 'loops',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [
                ['sport' => 'road', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 20_000],
            ],
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'One of the legs did not come back. Move the transition or a finish closer to a road, or try another length.');
});

it('asks a guest to log in before drawing a multisport plan', function (): void {
    $this->postJson(route('routes.multisport'), [])
        ->assertUnauthorized();
});
