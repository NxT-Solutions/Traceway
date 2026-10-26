<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\ValhallaCosting;

it('sends a paved flat road ride onto roads and away from rough surface', function (): void {
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

it('keeps a quiet ride off the busy roads', function (): void {
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

it('sends a hilly dirt mountain bike off the paved roads', function (): void {
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

it('rides a built point along its own street', function (): void {
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
    ], mustVisit: true);

    expect($payload['locations'][0]['radius'])->toBe(40)
        ->and($payload['locations'][1]['type'])->toBe('through')
        ->and($payload['locations'][1]['radius'])->toBe(15)
        ->and($payload['locations'][2])->not->toHaveKey('type')
        ->and($payload['locations'][2]['radius'])->toBe(15);
});

it('asks a loop to cross the start instead of turning around', function (): void {
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

it('sends a dirt build onto unpaved roads', function (): void {
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
    ], mustVisit: true);

    expect($payload['costing'])->toBe('bicycle')
        ->and($payload['costing_options']['bicycle']['bicycle_type'])->toBe('Cross')
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.1);
});

it('holds a ride-home bend to the area it was aimed at', function (): void {
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
    ], 90, mustVisit: false, viaRadius: 900);

    expect($payload['costing_options']['bicycle']['use_hills'])->toBe(0.0)
        ->and($payload['costing_options']['bicycle']['avoid_bad_surfaces'])->toBe(1.0)
        ->and($payload['costing_options']['bicycle']['use_roads'])->toBe(0.2)
        ->and($payload['locations'][1]['type'])->toBe('through')
        ->and($payload['locations'][1]['radius'])->toBe(900);
});

it('sends a dirt hike onto tracks and footpaths, not town streets', function (): void {
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
        ->and($payload['costing_options']['pedestrian']['walkway_factor'])->toBe(0.5)
        ->and($payload['costing_options']['pedestrian']['use_tracks'])->toBe(1.0)
        ->and($payload['costing_options']['pedestrian']['sidewalk_factor'])->toBe(1.0);
});

function filteredQuery(Sport $sport, Surface $surface, bool $quiet): RouteQuery
{
    return new RouteQuery(
        sport: $sport,
        shape: Shape::Loop,
        distanceMetres: 10_000,
        surface: $surface,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        preferQuiet: $quiet,
    );
}

dataset('foot sports', [
    'run' => [Sport::Run, 6.0, 1],
    'trail run' => [Sport::TrailRun, 6.0, 2],
    'walk' => [Sport::Walk, 5.0, 1],
    'hike' => [Sport::Hike, 5.0, 2],
]);

// Walkway factor, use_tracks, and use_living_streets for each surface and roads choice.
// Quiet first sets walkways and living streets. The surface sets tracks, and Dirt walkways too.
dataset('foot filters', [
    'paved, any road' => [Surface::Paved, false, 1.0, 0.5, 0.6],
    'paved, quiet first' => [Surface::Paved, true, 0.5, 0.5, 0.9],
    'any surface, any road' => [Surface::Mixed, false, 1.0, 0.75, 0.6],
    'any surface, quiet first' => [Surface::Mixed, true, 0.5, 0.75, 0.9],
    'dirt, any road' => [Surface::Dirt, false, 0.5, 1.0, 0.6],
    'dirt, quiet first' => [Surface::Dirt, true, 0.5, 1.0, 0.9],
]);

it('costs a foot route by its sport, surface, and roads', function (Sport $sport, float $speed, int $difficulty, Surface $surface, bool $quiet, float $walkway, float $tracks, float $livingStreets): void {
    $payload = ValhallaCosting::payload(filteredQuery($sport, $surface, $quiet), [[3.72, 51.05]]);

    expect($payload['costing'])->toBe('pedestrian')
        ->and($payload['costing_options'])->toBe(['pedestrian' => [
            'walking_speed' => $speed,
            'use_hills' => 0.5,
            'walkway_factor' => $walkway,
            'sidewalk_factor' => 1.0,
            'use_tracks' => $tracks,
            'use_living_streets' => $livingStreets,
            'max_hiking_difficulty' => $difficulty,
        ]]);
})->with('foot sports')->with('foot filters');

dataset('bike sports', [
    'road' => [Sport::Road, 'Road'],
    'gravel' => [Sport::Gravel, 'Cross'],
    'mountain bike' => [Sport::Mtb, 'Mountain'],
]);

// avoid_bad_surfaces and use_roads for each surface and roads choice.
dataset('bike filters', [
    'paved, any road' => [Surface::Paved, false, 1.0, 0.85],
    'paved, quiet first' => [Surface::Paved, true, 1.0, 0.2],
    'any surface, any road' => [Surface::Mixed, false, 0.35, 0.45],
    'any surface, quiet first' => [Surface::Mixed, true, 0.35, 0.2],
    'dirt, any road' => [Surface::Dirt, false, 0.0, 0.1],
    'dirt, quiet first' => [Surface::Dirt, true, 0.0, 0.1],
]);

it('costs a ride by its sport, surface, and roads', function (Sport $sport, string $bicycleType, Surface $surface, bool $quiet, float $badSurfaces, float $roads): void {
    $payload = ValhallaCosting::payload(filteredQuery($sport, $surface, $quiet), [[3.72, 51.05]]);

    expect($payload['costing'])->toBe('bicycle')
        ->and($payload['costing_options'])->toBe(['bicycle' => [
            'bicycle_type' => $bicycleType,
            'use_hills' => 0.5,
            'avoid_bad_surfaces' => $badSurfaces,
            'use_roads' => $roads,
        ]]);
})->with('bike sports')->with('bike filters');

it('matches a trail run or a hike onto the mountain paths it may use', function (): void {
    expect(ValhallaCosting::trace(Sport::TrailRun)['costing_options']['pedestrian'])->toBe(['walking_speed' => 6.0, 'max_hiking_difficulty' => 2])
        ->and(ValhallaCosting::trace(Sport::Hike)['costing_options']['pedestrian'])->toBe(['walking_speed' => 5.0, 'max_hiking_difficulty' => 2])
        ->and(ValhallaCosting::trace(Sport::Run)['costing_options']['pedestrian'])->toBe(['walking_speed' => 6.0, 'max_hiking_difficulty' => 1]);
});

it('gives every surface and roads choice on foot its own costing', function (): void {
    $costings = [];

    foreach (Surface::cases() as $surface) {
        foreach ([true, false] as $quiet) {
            $costings[] = ValhallaCosting::payload(filteredQuery(Sport::Walk, $surface, $quiet), [[3.72, 51.05]])['costing_options'];
        }
    }

    expect(array_unique(array_map(serialize(...), $costings)))->toHaveCount(6);
});

it('snaps a point with the limits its route is planned with', function (Sport $sport, Surface $surface): void {
    $route = ValhallaCosting::payload(filteredQuery($sport, $surface, quiet: true), [[3.72, 51.05]]);
    $snap = ValhallaCosting::locate($sport, $surface);
    $limits = $snap['costing_options'][$snap['costing']];

    // The options that decide which ways a route may use: the bike type
    // and its bad surfaces, or the walking speed and the hiking grade.
    expect($snap['costing'])->toBe($route['costing'])
        ->and(array_keys($limits))->toBe($snap['costing'] === 'bicycle' ? ['bicycle_type', 'avoid_bad_surfaces'] : ['walking_speed', 'max_hiking_difficulty'])
        ->and(array_intersect_key($route['costing_options'][$route['costing']], $limits))->toBe($limits);
})->with(Sport::cases())->with(Surface::cases());

it('leaves out the stretches the rider avoids', function (): void {
    $routeQuery = new RouteQuery(
        sport: Sport::Gravel,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Mixed,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        avoid: [new AvoidedStretch('bosweg', 51.0501, 3.7222, [[3.72, 51.0501], [3.724, 51.0501]])],
    );

    expect(ValhallaCosting::payload($routeQuery, [[3.72, 51.05], [3.8, 51.1], [3.72, 51.05]])['exclude_locations'])
        ->toBe([['lat' => 51.0501, 'lon' => 3.7222]])
        ->and(ValhallaCosting::payload($routeQuery, [[3.72, 51.05], [3.8, 51.1]], headings: [null, null])['exclude_locations'])
        ->toBe([['lat' => 51.0501, 'lon' => 3.7222]]);
});

it('leaves out every stretch on foot too', function (): void {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Hike,
        shape: Shape::Loop,
        distanceMetres: 10_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        avoid: [
            new AvoidedStretch('a', 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]]),
            new AvoidedStretch('b', 51.06, 3.73, [[3.73, 51.059], [3.73, 51.061]]),
        ],
    ), [[3.72, 51.05], [3.8, 51.1]]);

    expect($payload['exclude_locations'])->toHaveCount(2);
});

it('sends no excluded points when the rider avoids nothing', function (): void {
    $payload = ValhallaCosting::payload(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ), [[3.72, 51.05]]);

    expect($payload)->not->toHaveKey('exclude_locations');
});
