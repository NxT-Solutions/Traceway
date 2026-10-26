<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\Reshape;
use App\Domain\Routing\RouteChange;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

/**
 * A router that joins points with a bend half way, so a planned leg can be
 * told from a kept one, and remembers what it was asked.
 */
function bendingEngine(): RoutingEngine
{
    return new class implements RoutingEngine
    {
        /** @var list<array{kind: string, points: list<array{0: float, 1: float}>}> */
        public array $asked = [];

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new RuntimeException('Not used.');
        }

        public function candidates(RouteQuery $routeQuery): array
        {
            throw new RuntimeException('Not used.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->asked[] = ['kind' => 'trace', 'points' => $waypoints];

            return new GeneratedRoute($routeQuery, $this->bent($waypoints), 1_000, 0);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            $this->asked[] = ['kind' => 'home', 'points' => [$from, $to]];

            return new GeneratedRoute($routeQuery, $this->bent([$from, $to]), 1_000, 0);
        }

        /**
         * @param  list<array{0: float, 1: float}>  $points
         * @return list<array{0: float, 1: float}>
         */
        private function bent(array $points): array
        {
            $line = [];

            foreach ($points as $index => $point) {
                $previous = $points[$index - 1] ?? null;

                if ($previous !== null) {
                    $line[] = [($previous[0] + $point[0]) / 2, ($previous[1] + $point[1]) / 2 + 0.001];
                }

                $line[] = $point;
            }

            return $line;
        }
    };
}

function gravel(): RouteQuery
{
    return new RouteQuery(Sport::Gravel, Shape::Loop, 20_000, Surface::Mixed, ElevationBias::Neutral, 51.05, 3.70);
}

/** Four points of a square loop, and the straight line through them.
 * @return array<int, float[][]> */
function square(): array
{
    $waypoints = [[3.70, 51.05], [3.74, 51.05], [3.74, 51.08], [3.70, 51.08], [3.70, 51.05]];
    $line = [];

    foreach ($waypoints as $index => $point) {
        $previous = $waypoints[$index - 1] ?? null;

        if ($previous !== null) {
            $line[] = [($previous[0] + $point[0]) / 2, ($previous[1] + $point[1]) / 2];
        }

        $line[] = $point;
    }

    return [$line, $waypoints];
}

it('plans only the two legs around a moved point, and keeps the others as they were', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::move(2, [3.75, 51.085]));

    expect($reshaped->changed)->toBe([1, 2])
        ->and($reshaped->waypoints[2])->toBe([3.75, 51.085])
        ->and($routingEngine->asked)->toBe([['kind' => 'trace', 'points' => [[3.74, 51.05], [3.75, 51.085], [3.70, 51.08]]]])
        // The first and the last legs are the same points as before.
        ->and(array_slice($reshaped->coordinates, 0, 3))->toBe(array_slice($line, 0, 3))
        ->and(array_slice($reshaped->coordinates, -3))->toBe(array_slice($line, -3));
});

it('puts in a point between two others and plans only the leg it splits', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::insert(1, [3.72, 51.04]));

    expect($reshaped->waypoints)->toHaveCount(6)
        ->and($reshaped->changed)->toBe([0, 1])
        ->and($routingEngine->asked[0]['points'])->toBe([[3.70, 51.05], [3.72, 51.04], [3.74, 51.05]]);
});

it('takes a point out and joins its neighbours', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::remove(2));

    expect($reshaped->waypoints)->toHaveCount(4)
        ->and($reshaped->changed)->toBe([1])
        ->and($routingEngine->asked[0]['points'])->toBe([[3.74, 51.05], [3.70, 51.08]]);
});

it('adds a point at the end of a route', function (): void {
    $routingEngine = bendingEngine();
    $line = [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05]];

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, [[3.70, 51.05], [3.74, 51.05]], RouteChange::insert(2, [3.74, 51.07]));

    expect($reshaped->changed)->toBe([1])
        ->and($reshaped->coordinates[0])->toBe([3.70, 51.05])
        ->and($reshaped->coordinates[count($reshaped->coordinates) - 1])->toBe([3.74, 51.07]);
});

it('plans again only the legs asked for', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::reroute([3]));

    expect($reshaped->changed)->toBe([3])
        ->and($routingEngine->asked)->toBe([['kind' => 'trace', 'points' => [[3.70, 51.08], [3.70, 51.05]]]]);
});

it('finds a built loop its way home again, without riding back', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::move(3, [3.69, 51.085]), closing: true);

    expect(array_column($routingEngine->asked, 'kind'))->toBe(['trace', 'home'])
        ->and($routingEngine->asked[1]['points'])->toBe([[3.69, 51.085], [3.70, 51.05]]);
});

it('refuses a change that does not fit the route', function (): void {
    [$line, $waypoints] = square();

    expect(fn (): mixed => new Reshape(bendingEngine())->apply(gravel(), $line, $waypoints, RouteChange::move(9, [3.7, 51.0])))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): mixed => new Reshape(bendingEngine())->apply(gravel(), $line, $waypoints, RouteChange::reroute([7])))
        ->toThrow(InvalidArgumentException::class);
});

it('plans from where the line passes a stop, not from the field beside it', function (): void {
    [$line, $waypoints] = square();
    // Traceway aimed the second stop 300 m off the road; the loop passes it at the corner.
    $waypoints[1] = [3.744, 51.048];
    $routingEngine = bendingEngine();

    $reshaped = new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::move(2, [3.75, 51.085]));

    expect($routingEngine->asked[0]['points'][0])->toBe([3.74, 51.05])
        ->and($reshaped->waypoints[1])->toBe([3.74, 51.05])
        ->and(array_slice($reshaped->coordinates, 0, 3))->toBe(array_slice($line, 0, 3));
});

it("keeps the start, a loop's finish, and nothing past it", function (RouteChange $routeChange, bool $loop): void {
    [$line, $waypoints] = square();

    expect(fn (): mixed => new Reshape(bendingEngine())->apply(gravel(), $line, $waypoints, $routeChange, loop: $loop))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'moving the start' => [RouteChange::move(0, [3.71, 51.05]), false],
    "moving a loop's finish" => [RouteChange::move(4, [3.71, 51.05]), true],
    "taking out a loop's finish" => [RouteChange::remove(4), true],
    "a point after a loop's finish" => [RouteChange::insert(5, [3.71, 51.05]), true],
]);

it('refuses a place too far from the route to bend it there', function (): void {
    [$line, $waypoints] = square();
    $routingEngine = bendingEngine();

    expect(fn (): mixed => new Reshape($routingEngine)->apply(gravel(), $line, $waypoints, RouteChange::move(2, [-179.0, -89.0])))
        ->toThrow(InvalidArgumentException::class)
        ->and($routingEngine->asked)->toBe([]);
});

it('stops at the most points a route has', function (): void {
    $waypoints = array_map(fn (int $index): array => [3.70 + $index * 0.001, 51.05], range(0, Reshape::MOST_WAYPOINTS - 1));

    expect(fn (): mixed => new Reshape(bendingEngine())->apply(gravel(), $waypoints, $waypoints, RouteChange::insert(1, [3.7005, 51.051])))
        ->toThrow(InvalidArgumentException::class);
});
