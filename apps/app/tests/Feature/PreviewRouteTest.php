<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews a loop for a signed in rider', function (): void {
    $this->app->instance(RoutingEngine::class, new class implements RoutingEngine
    {
        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->candidates($routeQuery)[0];
        }

        /**
         * @return GeneratedRoute[]
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            return [new GeneratedRoute($routeQuery, [[3.72, 51.05], [3.8, 51.1]], 18_000, 90, waypoints: [[3.72, 51.05], [3.8, 51.1]])];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, $waypoints, 12_000, 40);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, [$from, $to], 4_000, 10);
        }
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.preview'), [
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'minimize',
            'distance_metres' => 20_000,
            'latitude' => 51.05,
            'longitude' => 3.72,
        ])
        ->assertOk()
        ->assertJsonPath('distance_metres', 18_000)
        ->assertJsonPath('routes.0.waypoints', [[3.72, 51.05], [3.8, 51.1]])
        ->assertJsonCount(1, 'routes');
});

it('builds a line through the rider points', function (): void {
    $this->app->instance(RoutingEngine::class, new class implements RoutingEngine
    {
        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->trace($routeQuery, [[3.72, 51.05]]);
        }

        /**
         * @return GeneratedRoute[]
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            return [$this->route($routeQuery)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, $waypoints, 12_000, 40);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            return new GeneratedRoute($routeQuery, [$from, [3.8, 51.02], $to], 6_000, 20);
        }
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.build'), [
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'roads' => 'quiet',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'follow' => true,
            'finish' => true,
            'points' => [
                ['latitude' => 51.08, 'longitude' => 3.75],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ascent_metres', 40)
        ->assertJsonStructure(['coordinates', 'finish_coordinates', 'legs_metres', 'user_metres']);
});

it('builds with the sport, surface, hills, roads, and length the rider chose', function (): void {
    $seen = (object) ['trace' => null, 'close' => null];

    $this->app->instance(RoutingEngine::class, new readonly class($seen) implements RoutingEngine
    {
        public function __construct(private object $seen) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->trace($routeQuery, [[4.9, 51.1]]);
        }

        /**
         * @return GeneratedRoute[]
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            return [$this->route($routeQuery)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->seen->trace = $routeQuery;

            return new GeneratedRoute($routeQuery, $waypoints, 6_000, 40);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            $this->seen->close = $routeQuery;

            return new GeneratedRoute($routeQuery, [$from, [4.95, 51.12], $to], 14_000, 80);
        }
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.build'), [
            'sport' => 'mtb',
            'surface' => 'dirt',
            'elevation' => 'maximize',
            'roads' => 'any',
            'distance_metres' => 20_000,
            'latitude' => 51.1,
            'longitude' => 4.9,
            'follow' => true,
            'finish' => true,
            'points' => [
                ['latitude' => 51.12, 'longitude' => 4.93],
            ],
        ])
        ->assertOk();

    expect($seen->trace)->toBeInstanceOf(RouteQuery::class)
        ->and($seen->trace->sport)->toBe(Sport::Mtb)
        ->and($seen->trace->surface)->toBe(Surface::Dirt)
        ->and($seen->trace->elevation)->toBe(ElevationBias::Maximize)
        ->and($seen->trace->preferQuiet)->toBeFalse()
        ->and($seen->trace->distanceMetres)->toBe(20_000)
        ->and($seen->close)->toBeInstanceOf(RouteQuery::class)
        ->and($seen->close->sport)->toBe(Sport::Mtb)
        ->and($seen->close->surface)->toBe(Surface::Dirt)
        ->and($seen->close->elevation)->toBe(ElevationBias::Maximize)
        ->and($seen->close->preferQuiet)->toBeFalse()
        ->and($seen->close->distanceMetres)->toBe(20_000);
});

it('asks a guest to log in before previewing a loop', function (): void {
    $this->postJson(route('routes.preview'), [])
        ->assertUnauthorized();
});
