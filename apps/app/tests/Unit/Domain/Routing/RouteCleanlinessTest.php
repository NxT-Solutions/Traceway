<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

it('keeps a selected point when cleaning would cut the road to it', function () {
    $line = [
        [4.860, 51.090],
        [4.862, 51.090],
        [4.8635, 51.090],
        [4.862, 51.090],
        [4.862, 51.0885],
    ];
    $point = [4.8635, 51.090];
    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect(RouteCleanliness::visits($trimmed, [$point]))->toBeFalse()
        ->and(RouteCleanliness::keepingVisits($trimmed, $line, [$point]))->toBe($line);
});

it('keeps a cleaned line that still passes the selected points', function () {
    $line = [
        [4.860, 51.090],
        [4.862, 51.090],
        [4.864, 51.090],
    ];
    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect(RouteCleanliness::keepingVisits($trimmed, $line, [[4.862, 51.090]]))->toBe($trimmed);
});

it('cuts a same-street turnaround out of the line', function () {
    $line = [
        [4.860, 51.090],
        [4.862, 51.090],
        [4.8635, 51.090],
        [4.862, 51.090],
        [4.862, 51.0885],
    ];

    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect($trimmed)->not->toContain([4.8635, 51.090])
        ->and($trimmed[0])->toBe([4.860, 51.090])
        ->and($trimmed[array_key_last($trimmed)])->toBe([4.862, 51.0885]);
});

it('drops the finish when it rides back along the opening road', function () {
    $line = [
        [4.860, 51.090],
        [4.862, 51.090],
        [4.862, 51.093],
        [4.866, 51.093],
        [4.866, 51.090],
        [4.862, 51.090],
        [4.860, 51.090],
    ];

    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect($trimmed)->toHaveCount(6)
        ->and($trimmed[0])->toBe([4.860, 51.090])
        ->and($trimmed[array_key_last($trimmed)])->toBe([4.862, 51.090]);
});

it('cuts a down-and-up of the same street', function () {
    $line = [
        [4.850, 51.100],
        [4.854, 51.100],
        [4.858, 51.100],
        [4.860, 51.100],
        [4.862, 51.100],
        [4.862, 51.098],
        [4.862, 51.096],
        [4.862, 51.094],
        [4.8624, 51.096],
        [4.8624, 51.098],
        [4.862, 51.100],
        [4.866, 51.100],
        [4.870, 51.100],
        [4.874, 51.100],
        [4.878, 51.100],
    ];

    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect($trimmed)->not->toContain([4.862, 51.094])
        ->and($trimmed[0])->toBe([4.850, 51.100])
        ->and($trimmed[array_key_last($trimmed)])->toBe([4.878, 51.100]);
});

it('finishes on the pin when the cut would stop the line up the road', function () {
    $line = [
        [4.860, 51.090],
        [4.864, 51.090],
        [4.868, 51.090],
        [4.872, 51.086],
        [4.872, 51.080],
        [4.864, 51.076],
        [4.856, 51.080],
        [4.856, 51.086],
        [4.864, 51.0902],
        [4.860, 51.090],
    ];

    $trimmed = RouteCleanliness::withoutSpurs($line);
    $end = $trimmed[array_key_last($trimmed)];

    expect($end[0])->toEqualWithDelta(4.860, 0.0004)
        ->and($end[1])->toEqualWithDelta(51.090, 0.0004);
});

it('cuts a hooked return that rides back beside the same street', function () {
    $line = [
        [4.850, 51.100],
        [4.854, 51.100],
        [4.858, 51.100],
        [4.862, 51.100],
        [4.866, 51.100],
        [4.866, 51.0982],
        [4.866, 51.0964],
        [4.866, 51.0946],
        [4.866, 51.0928],
        [4.8666, 51.0922],
        [4.8672, 51.0929],
        [4.86645, 51.0937],
        [4.86645, 51.0955],
        [4.86645, 51.0973],
        [4.866, 51.100],
        [4.870, 51.100],
        [4.874, 51.100],
        [4.878, 51.100],
        [4.882, 51.100],
        [4.886, 51.100],
    ];

    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect($trimmed)->not->toContain([4.866, 51.0928])
        ->and($trimmed)->not->toContain([4.8672, 51.0929])
        ->and($trimmed[0])->toBe([4.850, 51.100])
        ->and($trimmed[array_key_last($trimmed)])->toBe([4.886, 51.100])
        ->and(RouteCleanliness::reverseShare($trimmed))->toBeLessThan(0.05);
});

it('leaves a circuit that does not turn back on itself', function () {
    $circuit = [
        [0.0, 0.0],
        [0.002, 0.0],
        [0.002, 0.002],
        [0.0, 0.002],
        [0.0, 0.0],
    ];

    expect(RouteCleanliness::withoutSpurs($circuit))->toBe($circuit);
});

it('scores a there-and-back higher than a circuit', function () {
    $outAndBack = [
        [0.0, 0.0],
        [0.0, 0.002],
        [0.0, 0.004],
        [0.0, 0.002],
        [0.0, 0.0],
    ];
    $circuit = [
        [0.0, 0.0],
        [0.002, 0.0],
        [0.002, 0.002],
        [0.0, 0.002],
        [0.0, 0.0],
    ];

    expect(RouteCleanliness::reverseShare($outAndBack))->toBeGreaterThan(0.4)
        ->and(RouteCleanliness::reverseShare($circuit))->toBe(0.0);
});

it('keeps the circuit and drops the line that reverses', function () {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 10_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
    $reversed = new GeneratedRoute($query, [
        [0.0, 0.0],
        [0.0, 0.002],
        [0.0, 0.004],
        [0.0, 0.002],
        [0.0, 0.0],
    ], 10_000, 0);
    $circuit = new GeneratedRoute($query, [
        [0.0, 0.0],
        [0.002, 0.0],
        [0.002, 0.002],
        [0.0, 0.002],
        [0.0, 0.0],
    ], 10_200, 0);

    expect(RouteCleanliness::cleanest([$reversed, $circuit], 10_000)?->coordinates)->toBe($circuit->coordinates);
});

it('counts a return on the other side of the same road', function () {
    $offset = 0.00012;
    $line = [
        [4.0, 51.0],
        [4.004, 51.0],
        [4.008, 51.0],
        [4.008, 51.0 + $offset],
        [4.004, 51.0 + $offset],
        [4.0, 51.0 + $offset],
    ];

    expect(RouteCleanliness::reverseShare($line))->toBeGreaterThan(0.35);
});

it('prefers a clean loop over a doubled line of the right length', function () {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 10_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
    $doubled = new GeneratedRoute($query, [
        [4.0, 51.0],
        [4.004, 51.0],
        [4.008, 51.0],
        [4.008, 51.00012],
        [4.004, 51.00012],
        [4.0, 51.00012],
    ], 10_000, 0);
    $circuit = new GeneratedRoute($query, [
        [4.0, 51.0],
        [4.004, 51.0],
        [4.004, 51.004],
        [4.0, 51.004],
        [4.0, 51.0],
    ], 11_000, 0);

    expect(RouteCleanliness::cleanest([$doubled, $circuit], 10_000)?->distanceMetres)->toBe(11_000);
});

it('picks the clean loop closest to the asked distance', function () {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
    $circuit = [
        [4.0, 51.0],
        [4.02, 51.0],
        [4.02, 51.02],
        [4.0, 51.02],
        [4.0, 51.0],
    ];
    $short = new GeneratedRoute($query, $circuit, 15_600, 0);
    $close = new GeneratedRoute($query, $circuit, 19_400, 0);

    expect(RouteCleanliness::cleanest([$short, $close], 20_000)?->distanceMetres)->toBe(19_400);
});

it('offers another forward loop instead of a same-road turn-back', function () {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
    $forward = new GeneratedRoute($query, [
        [4.0, 51.0],
        [4.02, 51.0],
        [4.02, 51.02],
        [4.0, 51.02],
        [4.0, 51.0],
    ], 19_400, 0);
    $turnBack = new GeneratedRoute($query, [
        [4.1, 51.1],
        [4.1, 51.12],
        [4.1, 51.14],
        [4.1, 51.12],
        [4.1, 51.1],
    ], 15_100, 0);
    $shorter = new GeneratedRoute($query, [
        [4.3, 51.3],
        [4.32, 51.3],
        [4.32, 51.32],
        [4.3, 51.32],
        [4.3, 51.3],
    ], 12_600, 0);

    $offered = RouteCleanliness::diverse([$turnBack, $forward, $shorter], 20_000);

    expect($offered)->toHaveCount(2)
        ->and($offered[0]->distanceMetres)->toBe(19_400)
        ->and($offered[1]->distanceMetres)->toBe(12_600);
});

it('rejects a clean loop that is twice the requested length', function () {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    );
    $long = new GeneratedRoute($query, [
        [0.0, 0.0],
        [0.002, 0.0],
        [0.002, 0.002],
        [0.0, 0.002],
        [0.0, 0.0],
    ], 40_000, 0);
    $near = new GeneratedRoute($query, [
        [0.0, 0.0],
        [0.0, 0.002],
        [0.0, 0.004],
        [0.0, 0.002],
        [0.0, 0.0],
    ], 21_000, 0);

    expect(RouteCleanliness::cleanest([$long, $near], 20_000)?->distanceMetres)->toBe(21_000);
});
