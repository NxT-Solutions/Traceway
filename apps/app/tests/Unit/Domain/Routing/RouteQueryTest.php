<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

function query(int $distanceMetres = 40_000, float $latitude = 51.05, float $longitude = 3.72): RouteQuery
{
    return new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: $distanceMetres,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: $latitude,
        startLongitude: $longitude,
    );
}

it('accepts a ride between one and three hundred kilometres', function () {
    expect(query()->distanceMetres)->toBe(40_000);
});

it('rejects a distance outside the planning range', function (int $distanceMetres) {
    expect(fn () => query($distanceMetres))->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => [999],
    'too long' => [300_001],
]);

it('rejects a start pin off the map', function (float $latitude, float $longitude) {
    expect(fn () => query(latitude: $latitude, longitude: $longitude))->toThrow(InvalidArgumentException::class);
})->with([
    'latitude' => [91.0, 3.72],
    'longitude' => [51.05, 181.0],
]);
