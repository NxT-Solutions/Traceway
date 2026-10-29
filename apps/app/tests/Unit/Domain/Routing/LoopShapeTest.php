<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LoopShape;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

/**
 * Points given in kilometres east and north of a pin at 4.0, 51.0.
 *
 * @param  list<array{0: float, 1: float}>  $kilometres
 * @return list<array{0: float, 1: float}>
 */
function kmLine(array $kilometres): array
{
    return array_map(fn (array $point): array => [
        4.0 + $point[0] / (111.195 * cos(deg2rad(51.0))),
        51.0 + $point[1] / 111.195,
    ], $kilometres);
}

function shapeQuery(): RouteQuery
{
    return new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
}

it('passes a loop that goes round like one', function (): void {
    expect(LoopShape::misshapen(kmLine([[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]]), [4.0, 51.0]))->toBeFalse();
});

it('finds a figure of eight', function (): void {
    // The two halves cross 2 km from the pin and go round opposite ways.
    expect(LoopShape::misshapen(kmLine([[0, 0], [0, 2], [4, -2], [4, 2], [0, -2], [0, 0]]), [4.0, 51.0]))->toBeTrue();
});

it('finds a loop that comes back past the pin halfway round', function (): void {
    expect(LoopShape::misshapen(kmLine([[0, 0], [5, -1], [5, 4], [0.15, 0.15], [-5, 4], [-5, -1], [0, 0]]), [4.0, 51.0]))->toBeTrue();
});

it('finds a narrow finger out and back on two roads side by side', function (): void {
    // A 5 km square with a 3 km finger north from its top, out on one road
    // and back on another 60 m beside it.
    expect(LoopShape::misshapen(kmLine([[0, 0], [5, 0], [5, 5], [2.56, 5], [2.56, 8], [2.5, 8], [2.5, 5], [0, 5], [0, 0]]), [4.0, 51.0]))->toBeTrue();
});

it('leaves a piece of a route alone, since it is not a loop', function (): void {
    expect(LoopShape::misshapen(kmLine([[0, 2], [4, -2], [4, 2], [0, -2]]), [4.0, 51.0]))->toBeFalse();
});

it('offers a clean loop instead of a figure of eight on the length', function (): void {
    $eight = new GeneratedRoute(shapeQuery(), kmLine([[0, 0], [0, 2], [4, -2], [4, 2], [0, -2], [0, 0]]), 20_000, 0);
    $clean = new GeneratedRoute(shapeQuery(), kmLine([[0, 0], [5, 0], [5, 4], [0, 4], [0, 0]]), 18_000, 0);

    expect(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, RouteCleanliness::diverse([$eight, $clean], 20_000)))->toBe([18_000]);
});

it('still offers the figure of eight when nothing else came back', function (): void {
    $eight = new GeneratedRoute(shapeQuery(), kmLine([[0, 0], [0, 2], [4, -2], [4, 2], [0, -2], [0, 0]]), 20_000, 0);

    expect(RouteCleanliness::diverse([$eight], 20_000))->toBe([$eight]);
});
