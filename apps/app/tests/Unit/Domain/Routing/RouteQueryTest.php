<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

covers(RouteQuery::class);

uses()->group('mutation');

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

it('accepts a ride between one and three hundred kilometres', function (int $distanceMetres, float $latitude, float $longitude): void {
    $routeQuery = query($distanceMetres, $latitude, $longitude);

    expect($routeQuery->distanceMetres)->toBe($distanceMetres)
        ->and($routeQuery->startLatitude)->toBe($latitude)
        ->and($routeQuery->startLongitude)->toBe($longitude);
})->with([
    'a normal loop' => [40_000, 51.05, 3.72],
    'one kilometre at the south west corner' => [1_000, -90.0, -180.0],
    'three hundred kilometres at the north east corner' => [300_000, 90.0, 180.0],
]);

it('rejects a distance outside the planning range', function (int $distanceMetres): void {
    expect(fn (): RouteQuery => query($distanceMetres))->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => [999],
    'too long' => [300_001],
]);

it('rejects a start pin off the map', function (float $latitude, float $longitude): void {
    expect(fn (): RouteQuery => query(latitude: $latitude, longitude: $longitude))->toThrow(InvalidArgumentException::class);
})->with([
    'latitude high' => [90.1, 3.72],
    'latitude low' => [-90.1, 3.72],
    'longitude high' => [51.05, 180.1],
    'longitude low' => [51.05, -180.1],
]);

it('keeps a via point that sits on the map', function (float $latitude, float $longitude): void {
    $routeQuery = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.05,
        startLongitude: 3.72,
        viaLatitude: $latitude,
        viaLongitude: $longitude,
        preferQuiet: true,
    );

    expect($routeQuery->viaLatitude)->toBe($latitude)
        ->and($routeQuery->viaLongitude)->toBe($longitude)
        ->and($routeQuery->preferQuiet)->toBeTrue();
})->with([
    'south west corner' => [-90.0, -180.0],
    'north east corner' => [90.0, 180.0],
]);

it('rejects a via point that is only half set or off the map', function (?float $latitude, ?float $longitude): void {
    expect(fn (): RouteQuery => new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.05,
        startLongitude: 3.72,
        viaLatitude: $latitude,
        viaLongitude: $longitude,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'latitude only' => [51.0, null],
    'longitude only' => [null, 4.0],
    'latitude high' => [90.1, 4.0],
    'latitude low' => [-90.1, 4.0],
    'longitude high' => [51.0, 180.1],
    'longitude low' => [51.0, -180.1],
]);
