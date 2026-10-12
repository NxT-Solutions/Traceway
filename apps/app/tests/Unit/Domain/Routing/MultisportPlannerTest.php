<?php

declare(strict_types=1);

use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LandLeg;
use App\Domain\Routing\Layout;
use App\Domain\Routing\MultisportPlanner;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\MultisportRoute;
use App\Domain\Routing\PlannedLeg;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\SwimLeg;

covers(MultisportPlanner::class, MultisportRoute::class, PlannedLeg::class);

uses()->group('mutation');

/**
 * A routing engine that remembers what it was asked.
 */
function recordingEngine(bool $empty = false): RoutingEngine
{
    return new class($empty) implements RoutingEngine
    {
        /** @var list<RouteQuery> */
        public array $loops = [];

        /** @var list<array{query: RouteQuery, waypoints: list<array{0: float, 1: float}>}> */
        public array $traces = [];

        public function __construct(private readonly bool $empty) {}

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new LogicException('The planner does not ask for a single route.');
        }

        public function candidates(RouteQuery $routeQuery): array
        {
            $this->loops[] = $routeQuery;
            $start = [$routeQuery->startLongitude, $routeQuery->startLatitude];

            return $this->empty ? [] : [
                new GeneratedRoute($routeQuery, [$start, [3.8, 51.1], $start], $routeQuery->distanceMetres - 500, 40),
                new GeneratedRoute($routeQuery, [$start, [3.6, 51.0], $start], 1, 1),
            ];
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->traces[] = ['query' => $routeQuery, 'waypoints' => $waypoints];

            return new GeneratedRoute($routeQuery, $waypoints, 7_000, 15);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            throw new LogicException('The planner does not close loops.');
        }
    };
}

it('loops every land leg from the transition with its own sport and settings', function (): void {
    $routingEngine = recordingEngine();
    $plan = new MultisportPlanner($routingEngine)->plan(new MultisportQuery(Layout::Loops, 51.05, 3.72, [
        new LandLeg(Sport::Road, 40_000, Surface::Paved, ElevationBias::Minimize, preferQuiet: true),
        new LandLeg(Sport::Run, 10_000, Surface::Dirt, ElevationBias::Maximize),
    ]));

    expect($routingEngine->loops)->toHaveCount(2)
        ->and($routingEngine->traces)->toBe([])
        ->and($routingEngine->loops[0]->sport)->toBe(Sport::Road)
        ->and($routingEngine->loops[0]->shape)->toBe(Shape::Loop)
        ->and($routingEngine->loops[0]->distanceMetres)->toBe(40_000)
        ->and($routingEngine->loops[0]->surface)->toBe(Surface::Paved)
        ->and($routingEngine->loops[0]->elevation)->toBe(ElevationBias::Minimize)
        ->and($routingEngine->loops[0]->preferQuiet)->toBeTrue()
        ->and($routingEngine->loops[1]->sport)->toBe(Sport::Run)
        ->and($routingEngine->loops[1]->surface)->toBe(Surface::Dirt)
        ->and($routingEngine->loops[1]->elevation)->toBe(ElevationBias::Maximize)
        ->and($routingEngine->loops[1]->preferQuiet)->toBeFalse()
        ->and(array_map(fn (RouteQuery $routeQuery): array => [$routeQuery->startLongitude, $routeQuery->startLatitude], $routingEngine->loops))
        ->toBe([[3.72, 51.05], [3.72, 51.05]])
        ->and($plan->layout)->toBe(Layout::Loops)
        ->and(array_map(fn (PlannedLeg $plannedLeg): string => $plannedLeg->discipline(), $plan->legs))->toBe(['road', 'run'])
        ->and($plan->legs[0]->coordinates)->toBe([[3.72, 51.05], [3.8, 51.1], [3.72, 51.05]])
        ->and($plan->legs[0]->distanceMetres)->toBe(39_500)
        ->and($plan->legs[1]->distanceMetres)->toBe(9_500)
        ->and($plan->distanceMetres())->toBe(49_000)
        ->and($plan->ascentMetres())->toBe(80);
});

it('hands each chained leg over where the last one finished', function (): void {
    $routingEngine = recordingEngine();
    $plan = new MultisportPlanner($routingEngine)->plan(new MultisportQuery(Layout::Chain, 51.05, 3.72, [
        new LandLeg(Sport::Run, 5_000, Surface::Paved, ElevationBias::Neutral, finish: [3.75, 51.06]),
        new LandLeg(Sport::Gravel, 30_000, Surface::Mixed, ElevationBias::Neutral, preferQuiet: true, finish: [3.9, 51.2]),
        new LandLeg(Sport::Run, 5_000, Surface::Paved, ElevationBias::Neutral, finish: [3.95, 51.21]),
    ]));

    expect($routingEngine->loops)->toBe([])
        ->and(array_map(fn (array $trace): array => $trace['waypoints'], $routingEngine->traces))->toBe([
            [[3.72, 51.05], [3.75, 51.06]],
            [[3.75, 51.06], [3.9, 51.2]],
            [[3.9, 51.2], [3.95, 51.21]],
        ])
        ->and($routingEngine->traces[1]['query']->sport)->toBe(Sport::Gravel)
        ->and($routingEngine->traces[1]['query']->shape)->toBe(Shape::PointToPoint)
        ->and($routingEngine->traces[1]['query']->startLatitude)->toBe(51.06)
        ->and($routingEngine->traces[1]['query']->startLongitude)->toBe(3.75)
        ->and($routingEngine->traces[1]['query']->surface)->toBe(Surface::Mixed)
        ->and($routingEngine->traces[1]['query']->preferQuiet)->toBeTrue()
        ->and($plan->layout)->toBe(Layout::Chain)
        ->and(array_map(fn (PlannedLeg $plannedLeg): string => $plannedLeg->discipline(), $plan->legs))->toBe(['run', 'gravel', 'run'])
        ->and($plan->legs[2]->coordinates)->toBe([[3.9, 51.2], [3.95, 51.21]])
        ->and($plan->distanceMetres())->toBe(21_000)
        ->and($plan->ascentMetres())->toBe(45);
});

it('puts the swim first, as drawn', function (): void {
    $plan = new MultisportPlanner(recordingEngine())->plan(new MultisportQuery(
        Layout::Loops,
        51.05,
        3.72,
        [new LandLeg(Sport::Road, 20_000, Surface::Paved, ElevationBias::Neutral)],
        new SwimLeg([[3.71, 51.05], [3.71, 51.06], [3.72, 51.05]]),
    ));

    expect($plan->legs)->toHaveCount(2)
        ->and($plan->legs[0]->sport)->toBeNull()
        ->and($plan->legs[0]->discipline())->toBe('swim')
        ->and($plan->legs[0]->coordinates)->toBe([[3.71, 51.05], [3.71, 51.06], [3.72, 51.05]])
        ->and($plan->legs[0]->distanceMetres)->toBe(2_425)
        ->and($plan->legs[0]->ascentMetres)->toBe(0)
        ->and($plan->distanceMetres())->toBe(21_925);
});

it('says which loop did not come back', function (): void {
    expect(fn (): MultisportRoute => new MultisportPlanner(recordingEngine(empty: true))->plan(new MultisportQuery(Layout::Loops, 51.05, 3.72, [
        new LandLeg(Sport::Road, 20_000, Surface::Paved, ElevationBias::Neutral),
    ])))->toThrow(RuntimeException::class, 'No loop came back for leg 1.');
});

/**
 * A routing engine whose roads run a third longer than the straight line
 * through the waypoints.
 */
function measuringEngine(): RoutingEngine
{
    return new class implements RoutingEngine
    {
        /** @var list<list<array{0: float, 1: float}>> */
        public array $traces = [];

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new LogicException('The planner does not ask for a single route.');
        }

        public function candidates(RouteQuery $routeQuery): array
        {
            throw new LogicException('A chain does not ask for loops.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->traces[] = $waypoints;

            return new GeneratedRoute($routeQuery, $waypoints, (int) round(Distance::along($waypoints) * 1.3), 0);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            throw new LogicException('The planner does not close loops.');
        }
    };
}

it('stretches a chained leg to its length through a point to the side', function (): void {
    $routingEngine = measuringEngine();
    $plan = new MultisportPlanner($routingEngine)->plan(new MultisportQuery(Layout::Chain, 51.12, 4.84, [
        new LandLeg(Sport::Run, 12_000, Surface::Paved, ElevationBias::Neutral, finish: [4.88, 51.10], stretch: true),
    ]));

    expect($plan->legs[0]->distanceMetres)->toEqualWithDelta(12_000, 600)
        ->and($routingEngine->traces[0])->toBe([[4.84, 51.12], [4.88, 51.10]])
        ->and($routingEngine->traces[1])->toHaveCount(3)
        ->and($plan->legs[0]->coordinates[0])->toBe([4.84, 51.12])
        ->and(array_last($plan->legs[0]->coordinates))->toBe([4.88, 51.10]);
});

it('takes the shortest way when a chained leg does not stretch, or is already long enough', function (bool $stretch, int $length): void {
    $routingEngine = measuringEngine();
    $plan = new MultisportPlanner($routingEngine)->plan(new MultisportQuery(Layout::Chain, 51.12, 4.84, [
        new LandLeg(Sport::Run, $length, Surface::Paved, ElevationBias::Neutral, finish: [4.88, 51.10], stretch: $stretch),
    ]));

    expect($routingEngine->traces)->toHaveCount(1)
        ->and($plan->legs[0]->coordinates)->toBe([[4.84, 51.12], [4.88, 51.10]]);
})->with([
    'no stretch' => [false, 12_000],
    'already long enough' => [true, 4_000],
]);
