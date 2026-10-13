<?php

declare(strict_types=1);

use App\Domain\Courses\ImportedLine;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\FollowPoint;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

/**
 * A square loop of about 3.9 km.
 *
 * @return list<array{0: float, 1: float}>
 */
function squareLoop(): array
{
    return [[4.0, 51.0], [4.015, 51.0], [4.015, 51.01], [4.0, 51.01], [4.0, 51.0]];
}

function importQuery(): RouteQuery
{
    return new RouteQuery(Sport::Road, Shape::PointToPoint, 5_000, Surface::Paved, ElevationBias::Neutral, 51.0, 4.0);
}

/**
 * A follower that snaps with the given answer and routes through points
 * with the given drawing, and remembers each set of points it was given.
 *
 * @param  (Closure(list<FollowPoint>): GeneratedRoute)|null  $draw
 */
function follower(?GeneratedRoute $generatedRoute = null, ?Closure $draw = null): LineFollower
{
    return new class($generatedRoute, $draw) implements LineFollower
    {
        /** @var list<list<FollowPoint>> */
        public array $followed = [];

        public function __construct(private readonly ?GeneratedRoute $generatedRoute, private readonly ?Closure $draw) {}

        public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute
        {
            return $this->generatedRoute;
        }

        /**
         * @param  FollowPoint[]  $points
         */
        public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute
        {
            $this->followed[] = $points;

            if ($this->draw instanceof Closure) {
                return ($this->draw)($points);
            }

            $line = array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points);

            return new GeneratedRoute($routeQuery, $line, (int) round(Distance::along($line)), 0);
        }
    };
}

/**
 * The points, with a ride out and back on the road after the first one,
 * as a router does when it meets a point going the wrong way.
 *
 * @param  list<FollowPoint>  $points
 */
function withDetour(array $points): GeneratedRoute
{
    $line = array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points);
    $first = $line[0];
    $next = $line[1] ?? $first;
    $detour = [$first, $next, [$next[0] + 0.006, $next[1]], $next];
    $coordinates = array_merge($detour, array_slice($line, 2));

    return new GeneratedRoute(importQuery(), $coordinates, (int) round(Distance::along($coordinates)), 0);
}

it('knows a loop from a line that ends somewhere else', function (): void {
    expect(ImportedLine::isLoop(squareLoop()))->toBeTrue()
        ->and(ImportedLine::isLoop([[4.0, 51.0], [4.1, 51.0], [4.2, 51.0]]))->toBeFalse();
});

it('picks points along the line no further apart than a short ride, with the way the line goes', function (): void {
    $points = ImportedLine::points(squareLoop());

    expect($points[0]->point)->toBe([4.0, 51.0])
        ->and(array_last($points)?->point)->toBe([4.0, 51.0])
        ->and($points[0]->heading)->toBeGreaterThanOrEqual(88)->toBeLessThanOrEqual(92)
        ->and(array_last($points)?->heading)->toBeGreaterThanOrEqual(178)->toBeLessThanOrEqual(182);

    for ($index = 1, $count = count($points); $index < $count; $index++) {
        expect(Distance::metres($points[$index - 1]->point, $points[$index]->point))->toBeLessThanOrEqual(760.0);
    }
});

it('moves the points half a step along to try again', function (): void {
    $points = array_map(fn (FollowPoint $followPoint): array => $followPoint->point, ImportedLine::points(squareLoop()));
    $shifted = array_map(fn (FollowPoint $followPoint): array => $followPoint->point, ImportedLine::points(squareLoop(), 0.5));

    expect($shifted[0])->toBe($points[0])
        ->and(array_last($shifted))->toBe(array_last($points))
        ->and(array_intersect(array_map(json_encode(...), array_slice($shifted, 1, -1)), array_map(json_encode(...), array_slice($points, 1, -1))))->toBe([]);
});

it('rides a long line through a bounded number of points', function (): void {
    expect(count(ImportedLine::points([[4.0, 51.0], [6.5, 51.0]])))->toBeLessThanOrEqual(201);
});

it('starts a loop where it passes closest to the pin, and leaves any other line as it is', function (): void {
    $rotated = ImportedLine::fromNearest(squareLoop(), [4.0152, 51.0102]);
    $line = [[4.0, 51.0], [4.02, 51.0]];

    expect($rotated[0])->toBe([4.015, 51.01])
        ->and(array_last($rotated))->toBe([4.015, 51.01])
        ->and(ImportedLine::fromNearest($line, [3.9, 51.0]))->toBe($line);
});

it('keeps the line snapped onto the roads when it covers the file and goes forward', function (): void {
    $snapped = new GeneratedRoute(importQuery(), squareLoop(), (int) round(Distance::along(squareLoop())), 0);
    $lineFollower = follower($snapped);

    expect(ImportedLine::along($lineFollower, importQuery(), squareLoop()))->toBe($snapped)
        ->and($lineFollower->followed)->toBe([]);
});

it('draws through points when the snapped line stops part way', function (): void {
    $partWay = [[4.0, 51.0], [4.015, 51.0]];
    $lineFollower = follower(new GeneratedRoute(importQuery(), $partWay, 1_050, 0));

    $generatedRoute = ImportedLine::along($lineFollower, importQuery(), squareLoop());

    expect($lineFollower->followed)->toHaveCount(1)
        ->and($generatedRoute->distanceMetres)->toBeGreaterThan(3_800);
});

it('moves the points along when the line turns back on itself, and keeps the clean one', function (): void {
    $lineFollower = follower(draw: fn (array $points): GeneratedRoute => $points[1]->point === ImportedLine::points(squareLoop())[1]->point
        ? withDetour($points)
        : new GeneratedRoute(importQuery(), array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points), 4_400, 0));

    $generatedRoute = ImportedLine::along($lineFollower, importQuery(), squareLoop());

    expect($lineFollower->followed)->toHaveCount(2)
        ->and(RouteCleanliness::reverseShare($generatedRoute->coordinates))->toBeLessThan(0.01);
});

it('keeps the cleanest line when every try turns back a little', function (): void {
    $lineFollower = follower(draw: withDetour(...));

    $generatedRoute = ImportedLine::along($lineFollower, importQuery(), squareLoop());

    expect($lineFollower->followed)->toHaveCount(3)
        ->and($lineFollower->followed[2][1]->heading)->toBeNull()
        ->and($generatedRoute->coordinates)->not->toBe([]);
});

it('says so when no try joins up', function (): void {
    $lineFollower = follower(draw: fn (): GeneratedRoute => throw new RuntimeException('No road.'));

    expect(fn (): GeneratedRoute => ImportedLine::along($lineFollower, importQuery(), squareLoop()))
        ->toThrow(RuntimeException::class, 'No road.');
});

it('rides from the pin to the line, along it, and home by other roads', function (): void {
    $routingEngine = new class implements RoutingEngine
    {
        /** @var list<string> */
        public array $calls = [];

        public function route(RouteQuery $routeQuery): GeneratedRoute
        {
            throw new LogicException('Not used.');
        }

        public function candidates(RouteQuery $routeQuery): array
        {
            throw new LogicException('Not used.');
        }

        public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
        {
            $this->calls[] = 'trace';

            return new GeneratedRoute($routeQuery, $waypoints, 1_000, 3);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            $this->calls[] = 'closeLoop after '.count($ridden).' points';

            return new GeneratedRoute($routeQuery, [$from, [$from[0], $to[1]], $to], 1_500, 4);
        }
    };
    $home = [4.03, 51.02];

    $generatedRoute = ImportedLine::ride(follower(), $routingEngine, importQuery(), squareLoop(), $home);

    expect($generatedRoute->coordinates[0])->toBe($home)
        ->and(array_last($generatedRoute->coordinates))->toBe($home)
        ->and($routingEngine->calls[0])->toBe('trace')
        ->and($routingEngine->calls[1])->toStartWith('closeLoop after')
        ->and($generatedRoute->distanceMetres)->toBeGreaterThan(1_000 + 3_800 + 1_500)
        ->and($generatedRoute->ascentMetres)->toBe(7);
});

it('routes a long list of points a few dozen at a time and joins them', function (): void {
    $sizes = [];
    $lineFollower = follower(draw: function (array $points) use (&$sizes): GeneratedRoute {
        $sizes[] = count($points);

        return new GeneratedRoute(importQuery(), array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points), 1_000, 5);
    });
    $points = [];

    for ($index = 0; $index < 100; $index++) {
        $points[] = new FollowPoint([4.0 + $index * 0.001, 51.0], 90);
    }

    $generatedRoute = ImportedLine::follow($lineFollower, importQuery(), $points);

    expect($sizes)->toBe([40, 40, 22])
        ->and($generatedRoute->coordinates)->toBe(array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points))
        ->and($generatedRoute->distanceMetres)->toBe(3000)
        ->and($generatedRoute->ascentMetres)->toBe(15);
});
