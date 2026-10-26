<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Mcp\Servers\TracewayServer;
use App\Mcp\Tools\PlanLoopTool;
use App\Mcp\Tools\PlanMultisportTool;
use App\Models\AvoidedStretch as AvoidedStretchModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A router that remembers each plan it was asked for, and rides along the
 * Bosweg factory stretch so a response can say it still uses it.
 */
function avoidingEngine(): object
{
    return new class implements RoutingEngine
    {
        /** @var list<RouteQuery> */
        public array $asked = [];

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            return $this->candidates($routeQuery)[0];
        }

        /**
         * @return GeneratedRoute[]
         */
        public function candidates(RouteQuery $routeQuery): array
        {
            $this->asked[] = $routeQuery;
            $start = [$routeQuery->startLongitude, $routeQuery->startLatitude];

            return [new GeneratedRoute($routeQuery, [$start, [3.715, 51.05], [3.73, 51.05], $start], $routeQuery->distanceMetres, 10)];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->asked[] = $routeQuery;

            return new GeneratedRoute($routeQuery, $waypoints, 3_000, 10);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            $this->asked[] = $routeQuery;

            return new GeneratedRoute($routeQuery, [$from, $to], 1_000, 0);
        }
    };
}

/**
 * @param  list<RouteQuery>  $asked
 * @return list<list<string>>
 */
function avoidedNames(array $asked): array
{
    return array_map(
        fn (RouteQuery $routeQuery): array => array_map(fn (AvoidedStretch $avoidedStretch): string => (string) $avoidedStretch->name, $routeQuery->avoid),
        $asked,
    );
}

beforeEach(function (): void {
    $this->engine = avoidingEngine();
    $this->app->instance(RoutingEngine::class, $this->engine);
    $this->rider = User::factory()->create();

    AvoidedStretchModel::factory()->for($this->rider)->create(['name' => 'Bosweg']);
    AvoidedStretchModel::factory()->for($this->rider)->create(['name' => 'Far away', 'latitude' => 50.0, 'longitude' => 5.5, 'shape' => [[5.49, 50.0], [5.51, 50.0]]]);
    AvoidedStretchModel::factory()->create(['name' => 'Somebody else']);
});

it("skips the rider's own nearby roads on a loop, and says when it still rides one", function (): void {
    $this->actingAs($this->rider)
        ->postJson(route('routes.preview'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'distance_metres' => 30_000,
            'latitude' => 51.05,
            'longitude' => 3.72,
        ])
        ->assertOk()
        ->assertJsonCount(1, 'routes.0.crosses_avoided');

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg']]);
});

it('skips them on a built route and its way back', function (): void {
    $this->actingAs($this->rider)
        ->postJson(route('routes.build'), [
            'sport' => 'gravel',
            'surface' => 'mixed',
            'elevation' => 'neutral',
            'latitude' => 51.05,
            'longitude' => 3.71,
            'follow' => true,
            'finish' => true,
            'points' => [['latitude' => 51.06, 'longitude' => 3.73]],
        ])
        ->assertOk()
        ->assertJsonPath('crosses_avoided', []);

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg'], ['Bosweg']]);
});

it('skips them on every leg of a multisport plan', function (): void {
    $this->actingAs($this->rider)
        ->postJson(route('routes.multisport'), [
            'layout' => 'loops',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [
                ['sport' => 'road', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 20_000],
                ['sport' => 'run', 'surface' => 'mixed', 'elevation' => 'neutral', 'distance_metres' => 5_000],
            ],
        ])
        ->assertOk();

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg'], ['Bosweg']]);
});

it('skips them when an AI assistant plans a loop or a brick', function (): void {
    $this->app->instance(PlaceFinder::class, new class implements PlaceFinder
    {
        public function find(string $search): Place
        {
            return new Place('Ghent, Belgium', 51.05, 3.72);
        }
    });

    TracewayServer::actingAs($this->rider)
        ->tool(PlanLoopTool::class, ['start' => 'Ghent', 'sport' => 'gravel', 'distance_km' => 30])
        ->assertOk();

    TracewayServer::actingAs($this->rider)
        ->tool(PlanMultisportTool::class, ['start' => 'Ghent', 'legs' => [['sport' => 'road', 'distance_km' => 20], ['sport' => 'run', 'distance_km' => 5]]])
        ->assertOk();

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg'], ['Bosweg'], ['Bosweg']]);
});

it('sends nothing to skip for a rider who avoids nothing', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.preview'), [
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'distance_metres' => 30_000,
            'latitude' => 51.05,
            'longitude' => 3.72,
        ])
        ->assertOk()
        ->assertJsonPath('routes.0.crosses_avoided', []);

    expect(avoidedNames($this->engine->asked))->toBe([[]]);
});

it('sends a path avoided on foot with a run, not with a ride', function (): void {
    AvoidedStretchModel::factory()->for($this->rider)->create([
        'name' => 'Footpath',
        'sport' => 'hike',
        'surface' => 'dirt',
        'latitude' => 51.052,
        'shape' => [[3.72, 51.052], [3.724, 51.052]],
    ]);

    foreach (['gravel' => 'mixed', 'run' => 'paved'] as $sport => $surface) {
        $this->actingAs($this->rider)
            ->postJson(route('routes.preview'), [
                'sport' => $sport,
                'surface' => $surface,
                'elevation' => 'neutral',
                'distance_metres' => 10_000,
                'latitude' => 51.05,
                'longitude' => 3.72,
            ])
            ->assertOk();
    }

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg'], ['Bosweg', 'Footpath']]);
});

it('gives each multisport leg the roads for its own sport', function (): void {
    AvoidedStretchModel::factory()->for($this->rider)->create([
        'name' => 'Footpath',
        'sport' => 'run',
        'latitude' => 51.052,
        'shape' => [[3.72, 51.052], [3.724, 51.052]],
    ]);

    $this->actingAs($this->rider)
        ->postJson(route('routes.multisport'), [
            'layout' => 'loops',
            'latitude' => 51.05,
            'longitude' => 3.72,
            'legs' => [
                ['sport' => 'road', 'surface' => 'paved', 'elevation' => 'neutral', 'distance_metres' => 20_000],
                ['sport' => 'run', 'surface' => 'mixed', 'elevation' => 'neutral', 'distance_metres' => 5_000],
            ],
        ])
        ->assertOk();

    expect(avoidedNames($this->engine->asked))->toBe([['Bosweg'], ['Bosweg', 'Footpath']]);
});
