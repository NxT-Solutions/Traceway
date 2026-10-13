<?php

declare(strict_types=1);

use App\Domain\Courses\ImportedLine;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

/**
 * A square loop of about 4.4 km.
 *
 * @return list<array{0: float, 1: float}>
 */
function squareLoop(): array
{
    return [[4.0, 51.0], [4.015, 51.0], [4.015, 51.01], [4.0, 51.01], [4.0, 51.0]];
}

it('knows a loop from a line that ends somewhere else', function (): void {
    expect(ImportedLine::isLoop(squareLoop()))->toBeTrue()
        ->and(ImportedLine::isLoop([[4.0, 51.0], [4.1, 51.0], [4.2, 51.0]]))->toBeFalse();
});

it('picks points along the line no further apart than a short ride', function (): void {
    $waypoints = ImportedLine::waypoints(squareLoop());

    expect($waypoints[0])->toBe([4.0, 51.0])
        ->and(array_last($waypoints))->toBe([4.0, 51.0]);
    $counter = count($waypoints);

    for ($index = 1; $index < $counter; $index++) {
        expect(Distance::metres($waypoints[$index - 1], $waypoints[$index]))->toBeLessThanOrEqual(760.0);
    }
});

it('rides a long line through a bounded number of points', function (): void {
    expect(count(ImportedLine::waypoints([[4.0, 51.0], [6.5, 51.0]])))->toBeLessThanOrEqual(201);
});

it('reverses the line', function (): void {
    $waypoints = ImportedLine::reversed([[4.0, 51.0], [4.05, 51.0]]);

    expect($waypoints[0])->toBe([4.05, 51.0])
        ->and(array_last($waypoints))->toBe([4.0, 51.0]);
});

it('starts a loop where it passes closest to the pin, and comes home', function (): void {
    $home = [4.0152, 51.0102];
    $waypoints = ImportedLine::fromStart(squareLoop(), $home);

    expect($waypoints[0])->toBe($home)
        ->and(array_last($waypoints))->toBe($home)
        ->and(Distance::metres($waypoints[1], [4.015, 51.01]))->toBeLessThan(800.0);
});

it('rides out to a line that does not start at home, along it, and back', function (): void {
    $home = [3.9, 51.0];
    $waypoints = ImportedLine::fromStart([[4.0, 51.0], [4.02, 51.0]], $home);

    expect($waypoints[0])->toBe($home)
        ->and($waypoints[1])->toBe([4.0, 51.0])
        ->and($waypoints[count($waypoints) - 2])->toBe([4.02, 51.0])
        ->and(array_last($waypoints))->toBe($home);
});

it('routes a long list of points a few dozen at a time and joins them', function (): void {
    $routingEngine = new class implements RoutingEngine
    {
        /** @var list<int> */
        public array $sizes = [];

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
            $this->sizes[] = count($waypoints);

            return new GeneratedRoute($routeQuery, $waypoints, 1000, 5);
        }

        public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
        {
            throw new LogicException('Not used.');
        }
    };
    $waypoints = [];

    for ($index = 0; $index < 100; $index++) {
        $waypoints[] = [4.0 + $index * 0.001, 51.0];
    }

    $generatedRoute = ImportedLine::trace($routingEngine, new RouteQuery(Sport::Road, Shape::PointToPoint, 5_000, Surface::Paved, ElevationBias::Neutral, 51.0, 4.0), $waypoints);

    expect($routingEngine->sizes)->toBe([40, 40, 22])
        ->and($generatedRoute->coordinates)->toBe($waypoints)
        ->and($generatedRoute->distanceMetres)->toBe(3000)
        ->and($generatedRoute->ascentMetres)->toBe(15);
});
