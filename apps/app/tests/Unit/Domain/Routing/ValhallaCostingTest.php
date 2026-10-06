<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\ValhallaCosting;

it('sends a paved flat road ride onto roads and away from rough surface', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [[3.72, 51.05]]);

    expect($payload['costing'])->toBe('bicycle')
        ->and($payload['costing_options']['bicycle']['bicycle_type'])->toBe('Road')
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(1.0)
        ->and($payload['costing_options']['bicycle']['use_hills'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.85);
});

it('keeps a quiet ride off the busy roads', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        preferQuiet: true,
    ), [[3.72, 51.05]]);

    expect($payload['costing_options']['bicycle']['use_roads'])->toBe(0.2);
});

it('sends a hilly dirt mountain bike off the paved roads', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Mtb,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Maximize,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [[3.72, 51.05]]);

    expect($payload['costing_options']['bicycle']['bicycle_type'])->toBe('Mountain')
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['use_hills'])->toBe(1.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.1);
});

it('rides a built point along its own street', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [
        [3.72, 51.05],
        [3.74, 51.06],
        [3.75, 51.04],
    ], null, true);

    expect($payload['locations'][0]['radius'])->toBe(40)
        ->and($payload['locations'][1]['type'])->toBe('through')
        ->and($payload['locations'][1]['radius'])->toBe(15)
        ->and($payload['locations'][2])->not->toHaveKey('type')
        ->and($payload['locations'][2]['radius'])->toBe(15);
});

it('asks a loop to cross the start instead of turning around', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [
        [3.72, 51.05],
        [3.74, 51.06],
        [3.70, 51.06],
        [3.72, 51.05],
    ]);

    expect($payload['locations'][1]['type'])->toBe('via')
        ->and($payload['locations'][2]['type'])->toBe('via')
        ->and($payload['locations'][0]['heading'])->toBe($payload['locations'][3]['heading'])
        ->and($payload['locations'][0]['heading_tolerance'])->toBe(120);
});

it('sends a dirt build onto unpaved roads', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Gravel,
        shape: Shape::PointToPoint,
        distanceMetres: 20_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        preferQuiet: true,
    ), [
        [3.72, 51.05],
        [3.74, 51.06],
    ], null, true);

    expect($payload['costing'])->toBe('bicycle')
        ->and($payload['costing_options']['bicycle']['bicycle_type'])->toBe('Cross')
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.1);
});

it('holds a ride-home bend to the area it was aimed at', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.05,
        startLongitude: 3.72,
        preferQuiet: true,
    ), [
        [3.75, 51.06],
        [3.80, 51.08],
        [3.72, 51.05],
    ], 90, false, 900);

    expect($payload['costing_options']['bicycle']['use_hills'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(1.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.2)
        ->and($payload['locations'][1]['type'])->toBe('through')
        ->and($payload['locations'][1]['radius'])->toBe(900);
});

it('sends a hike on foot', function () {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Hike,
        shape: Shape::Loop,
        distanceMetres: 8_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Maximize,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [[3.72, 51.05]]);

    expect($payload['costing'])->toBe('pedestrian')
        ->and($payload['costing_options']['pedestrian']['use_hills'])->toBe(1.0)
        ->and($payload['costing_options']['pedestrian']['sidewalk_factor'])->toBe(0.1);
});
