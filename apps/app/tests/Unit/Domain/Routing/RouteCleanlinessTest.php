<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LengthBias;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

it('keeps a selected point when cleaning would cut the road to it', function (): void {
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

it('keeps a cleaned line that still passes the selected points', function (): void {
    $line = [
        [4.860, 51.090],
        [4.862, 51.090],
        [4.864, 51.090],
    ];
    $trimmed = RouteCleanliness::withoutSpurs($line);

    expect(RouteCleanliness::keepingVisits($trimmed, $line, [[4.862, 51.090]]))->toBe($trimmed);
});

it('cuts a same-street turnaround out of the line', function (): void {
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
        ->and(array_last($trimmed))->toBe([4.862, 51.0885]);
});

it('cuts a small knot tied in the road, and carries on from where it closed', function (): void {
    // 7 km east along one road, with an 800 m triangle tied in it halfway.
    $line = lineThrough([
        [4.0, 51.0], [4.05, 51.0], [4.052, 50.998], [4.048, 50.998], [4.05, 51.0], [4.1, 51.0],
    ]);

    $trimmed = RouteCleanliness::withoutKnots($line);

    expect(min(array_column($trimmed, 1)))->toBeGreaterThan(50.9999)
        ->and($trimmed[0])->toBe([4.0, 51.0])
        ->and(array_last($trimmed))->toBe([4.1, 51.0]);
});

it('drops a lasso: the road out, the knot at its end, and the road back', function (): void {
    // A 20 km square, with a 500 m road south off its bottom side and a small triangle at the end of it.
    $line = lineThrough([
        [4.0, 51.0], [4.035, 51.0], [4.035, 50.9955], [4.039, 50.9935], [4.031, 50.9935], [4.035, 50.9955],
        [4.035, 51.0], [4.07, 51.0], [4.07, 51.045], [4.0, 51.045], [4.0, 51.0],
    ]);

    expect(min(array_column(RouteCleanliness::withoutSpurs($line), 1)))->toBeGreaterThan(50.9998);
});

it('keeps a lobe too long to be a knot', function (): void {
    // The same road, with a 5 km lobe south of it: a figure of eight, not a knot.
    $line = lineThrough([
        [4.0, 51.0], [4.05, 51.0], [4.07, 50.985], [4.03, 50.985], [4.05, 51.0], [4.1, 51.0],
    ]);

    expect(RouteCleanliness::withoutKnots($line))->toBe($line);
});

it('drops the finish when it rides back along the opening road', function (): void {
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
        ->and(array_last($trimmed))->toBe([4.862, 51.090]);
});

it('cuts a down-and-up of the same street', function (): void {
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
        ->and(array_last($trimmed))->toBe([4.878, 51.100]);
});

it('finishes on the pin when the cut would stop the line up the road', function (): void {
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
    $end = array_last($trimmed);

    expect($end[0])->toEqualWithDelta(4.860, 0.0004)
        ->and($end[1])->toEqualWithDelta(51.090, 0.0004);
});

it('cuts a hooked return that rides back beside the same street', function (): void {
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
        ->and(array_last($trimmed))->toBe([4.886, 51.100])
        ->and(RouteCleanliness::reverseShare($trimmed))->toBeLessThan(0.05);
});

it('leaves a circuit that does not turn back on itself', function (): void {
    $circuit = [
        [0.0, 0.0],
        [0.002, 0.0],
        [0.002, 0.002],
        [0.0, 0.002],
        [0.0, 0.0],
    ];

    expect(RouteCleanliness::withoutSpurs($circuit))->toBe($circuit);
});

it('scores a there-and-back higher than a circuit', function (): void {
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

it('keeps the circuit and drops the line that reverses', function (): void {
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

it('counts a return on the other side of the same road', function (): void {
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

it('prefers a clean loop over a doubled line of the right length', function (): void {
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

it('picks the clean loop closest to the asked distance', function (): void {
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

it('offers other forward loops before a same-road turn-back', function (): void {
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
    ], 18_200, 0);

    $offered = RouteCleanliness::diverse([$turnBack, $forward, $shorter], 20_000);

    expect(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, $offered))->toBe([19_400, 18_200, 15_100]);
});

it('rejects a clean loop that is twice the requested length', function (): void {
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

it('lets the loop ride its start street both ways', function (): void {
    // 1 km up the start street, a 10 km square, and the same 1 km home.
    $line = lineThrough([
        [4.0, 51.0],
        [4.0, 51.009],
        [4.035, 51.009],
        [4.035, 51.0315],
        [4.0, 51.0315],
        [4.0, 51.009],
        [4.0, 51.0],
    ]);

    expect(RouteCleanliness::reverseShare($line))->toBeGreaterThan(0.05)
        ->and(RouteCleanliness::doubledMetres($line))->toBe(0.0);
});

it('counts a long road to the loop past the way home', function (): void {
    // 4 km out to a 10 km square and 4 km back: more than a start street.
    $line = lineThrough([
        [4.0, 51.0],
        [4.0, 51.036],
        [4.035, 51.036],
        [4.035, 51.0585],
        [4.0, 51.0585],
        [4.0, 51.036],
        [4.0, 51.0],
    ]);

    expect(RouteCleanliness::doubledMetres($line))->toBeGreaterThan(RouteCleanliness::DOUBLED_METRES);
});

it('ranks a long loop that rides 2 km back down one road last, however small a share that is', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 44_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // A 40 km square with a 2 km road out of its north side and back.
    $doubled = lineThrough([
        [4.0, 51.0],
        [4.143, 51.0],
        [4.143, 51.09],
        [4.07, 51.09],
        [4.07, 51.108],
        [4.07, 51.09],
        [4.0, 51.09],
        [4.0, 51.0],
    ]);
    $clean = lineThrough([
        [4.0, 51.0],
        [4.143, 51.0],
        [4.143, 51.09],
        [4.0, 51.09],
        [4.0, 51.0],
    ]);
    $turnBack = new GeneratedRoute($query, $doubled, 44_000, 0);
    $loop = new GeneratedRoute($query, $clean, 40_000, 0);

    expect(RouteCleanliness::reverseShare($doubled))->toBeLessThan(0.12)
        ->and(RouteCleanliness::cleanest([$turnBack, $loop], 44_000)?->distanceMetres)->toBe(40_000)
        ->and(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, RouteCleanliness::diverse([$turnBack, $loop], 44_000)))->toBe([40_000, 44_000]);
});

it('measures the ride home the same however densely the road is drawn', function (): void {
    // 1.6 km up the opening road, a 20 km square, and the same 1.6 km home:
    // just past the 1.5 km a ride home may take.
    $corners = [
        [4.0, 51.0],
        [4.0, 51.0144],
        [4.0714, 51.0144],
        [4.0714, 51.0594],
        [4.0, 51.0594],
        [4.0, 51.0144],
        [4.0, 51.0],
    ];
    $sparse = RouteCleanliness::doubledMetres(lineThrough($corners, 1));
    $dense = RouteCleanliness::doubledMetres(lineThrough($corners, 10));

    expect($sparse)->toBeGreaterThan(0.0)->toBeLessThan(200.0)
        ->and($dense)->toBeGreaterThan(0.0)->toBeLessThan(200.0);
});

it('counts a road drawn with points a few metres apart', function (): void {
    // The 2 km road out of the square's north side and back, with a point every 5 metres.
    $line = lineThrough([
        [4.0, 51.0],
        [4.143, 51.0],
        [4.143, 51.09],
        [4.07, 51.09],
        [4.07, 51.108],
        [4.07, 51.09],
        [4.0, 51.09],
        [4.0, 51.0],
    ], 400);

    expect(RouteCleanliness::doubledMetres($line))->toBeGreaterThan(1_500.0);
});

it('offers the loop near the length that rides back before a clean loop far off it', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 44_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // A 40 km square with a 4 km road out of its north side and back, and a clean square half as big again.
    $turnBack = new GeneratedRoute($query, lineThrough([
        [4.0, 51.0], [4.143, 51.0], [4.143, 51.09], [4.07, 51.09], [4.07, 51.126], [4.07, 51.09], [4.0, 51.09], [4.0, 51.0],
    ]), 44_000, 0);
    $farOff = new GeneratedRoute($query, lineThrough([
        [4.0, 51.0], [4.215, 51.0], [4.215, 51.135], [4.0, 51.135], [4.0, 51.0],
    ]), 64_000, 0);

    $offered = RouteCleanliness::diverse([$farOff, $turnBack], 44_000);

    expect(RouteCleanliness::cleanest([$farOff, $turnBack], 44_000)?->distanceMetres)->toBe(44_000)
        ->and(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, $offered))->toBe([44_000, 64_000]);
});

it('picks the loop with the least road ridden twice when every loop rides back', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 46_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // The same 40 km square, with 2 km or, nearer the length, 3 km back down a road.
    $less = new GeneratedRoute($query, lineThrough([
        [4.0, 51.0], [4.143, 51.0], [4.143, 51.09], [4.07, 51.09], [4.07, 51.108], [4.07, 51.09], [4.0, 51.09], [4.0, 51.0],
    ]), 44_000, 0);
    $more = new GeneratedRoute($query, lineThrough([
        [4.0, 51.0], [4.143, 51.0], [4.143, 51.09], [4.07, 51.09], [4.07, 51.117], [4.07, 51.09], [4.0, 51.09], [4.0, 51.0],
    ]), 46_000, 0);

    expect(RouteCleanliness::cleanest([$more, $less], 46_000)?->distanceMetres)->toBe(44_000);
});

it('allows 15% off a short loop and a smaller share of a longer one', function (int $kilometres, float $tolerance): void {
    expect(RouteCleanliness::lengthTolerance($kilometres * 1_000))->toEqualWithDelta($tolerance, 1.0);
})->with([
    '5 km' => [5, 750.0],
    '10 km' => [10, 1_500.0],
    '20 km' => [20, 2_236.0],
    '60 km' => [60, 3_873.0],
    '100 km' => [100, 5_000.0],
    '200 km' => [200, 7_071.0],
]);

it('offers the loops within the tolerance of a 30 km run first, then the next closest', function (): void {
    $query = new RouteQuery(
        sport: Sport::Run,
        shape: Shape::Loop,
        distanceMetres: 30_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    $loops = array_map(
        fn (int $metres, float $east): GeneratedRoute => new GeneratedRoute($query, [
            [$east, 51.0], [$east + 0.02, 51.0], [$east + 0.02, 51.02], [$east, 51.02], [$east, 51.0],
        ], $metres, 0),
        [29_000, 19_900, 25_400, 27_300],
        [4.0, 4.1, 4.2, 4.3],
    );

    $offered = RouteCleanliness::diverse($loops, 30_000);

    expect(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, $offered))->toBe([29_000, 27_300, 25_400]);
});

it('offers the loops of a long ride within 5 km of 100 km first', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 100_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    $loops = array_map(
        fn (int $metres, float $east): GeneratedRoute => new GeneratedRoute($query, [
            [$east, 51.0], [$east + 0.02, 51.0], [$east + 0.02, 51.02], [$east, 51.02], [$east, 51.0],
        ], $metres, 0),
        [99_000, 93_800, 104_600, 85_000],
        [4.0, 4.1, 4.2, 4.3],
    );

    $offered = RouteCleanliness::diverse($loops, 100_000);

    expect(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, $offered))->toBe([99_000, 104_600, 93_800]);
});

it('does not let a flatter loop win by coming out further off than a loop may', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 100_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // 8 km long and dead flat, against right on the length and 15 m a kilometre.
    $flat = new GeneratedRoute($query, [[4.1, 51.0], [4.12, 51.0], [4.12, 51.02], [4.1, 51.02], [4.1, 51.0]], 108_000, 0);
    $hilly = new GeneratedRoute($query, [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.02], [4.0, 51.0]], 100_000, 1_500);

    expect(RouteCleanliness::cleanest([$flat, $hilly], 100_000)?->distanceMetres)->toBe(100_000);
});

it('offers three loops through a via point that reach it from different sides', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // Each goes out a different way, passes the via at the top halfway round, and comes home another way.
    $loops = array_map(
        fn (float $west, float $east, int $metres): GeneratedRoute => new GeneratedRoute($query, lineThrough([
            [4.0, 51.0], [$west, 51.05], [4.0, 51.1], [$east, 51.05], [4.0, 51.0],
        ]), $metres, 0),
        [3.9, 3.95, 4.05],
        [4.1, 4.15, 3.85],
        [40_000, 40_400, 39_600],
    );

    expect(RouteCleanliness::diverse($loops, 40_000))->toHaveCount(3);
});

it('keeps a most or a least before the closest length', function (LengthBias $lengthBias, array $offered): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 100_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
        lengthBias: $lengthBias,
    );
    $loops = array_map(
        fn (int $metres, float $east): GeneratedRoute => new GeneratedRoute($query, [
            [$east, 51.0], [$east + 0.02, 51.0], [$east + 0.02, 51.02], [$east, 51.02], [$east, 51.0],
        ], $metres, 0),
        [100_400, 97_000, 103_500, 92_000],
        [4.0, 4.1, 4.2, 4.3],
    );

    expect(array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->distanceMetres, RouteCleanliness::diverse($loops, 100_000)))->toBe($offered);
})->with([
    'closest' => [LengthBias::Closest, [100_400, 97_000, 103_500]],
    // Under it first, then the closest of what is over.
    'at most' => [LengthBias::AtMost, [97_000, 92_000, 100_400]],
    'at least' => [LengthBias::AtLeast, [100_400, 103_500, 97_000]],
]);

it('aims a most a little under the length and a least a little over', function (LengthBias $lengthBias, int $aimed): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 100_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
        lengthBias: $lengthBias,
    );

    expect(RouteCleanliness::aimMetres($query))->toBe($aimed);
})->with([
    'closest' => [LengthBias::Closest, 100_000],
    'at most' => [LengthBias::AtMost, 97_500],
    'at least' => [LengthBias::AtLeast, 102_500],
]);

it('still offers the best loop when every loop is far off the length', function (): void {
    $query = new RouteQuery(
        sport: Sport::Run,
        shape: Shape::Loop,
        distanceMetres: 30_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    $loop = new GeneratedRoute($query, [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.02], [4.0, 51.0]], 19_900, 0);

    expect(RouteCleanliness::diverse([$loop], 30_000))->toBe([$loop]);
});

it('picks the flatter loop for flat and the hillier one for hilly', function (ElevationBias $elevationBias, int $chosenAscent): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Paved,
        elevation: $elevationBias,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // The hilly loop is right on the length and climbs 15 m a kilometre. The flat one is 5% long and climbs 2.
    $hilly = new GeneratedRoute($query, [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.02], [4.0, 51.0]], 40_000, 600);
    $flat = new GeneratedRoute($query, [[4.1, 51.0], [4.12, 51.0], [4.12, 51.02], [4.1, 51.02], [4.1, 51.0]], 42_000, 84);

    expect(RouteCleanliness::cleanest([$flat, $hilly], 40_000)?->ascentMetres)->toBe($chosenAscent);
})->with([
    'flat' => [ElevationBias::Minimize, 84],
    'neutral' => [ElevationBias::Neutral, 600],
    'hilly' => [ElevationBias::Maximize, 600],
]);

it('lets the climb pick only among loops near the length', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 40_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // A dead flat loop half the length loses to a steep one on it.
    $steep = new GeneratedRoute($query, [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.02], [4.0, 51.0]], 40_000, 2_000);
    $short = new GeneratedRoute($query, [[4.1, 51.0], [4.12, 51.0], [4.12, 51.02], [4.1, 51.02], [4.1, 51.0]], 20_000, 0);

    expect(RouteCleanliness::cleanest([$short, $steep], 40_000)?->distanceMetres)->toBe(40_000);
});

/**
 * A line through the corners, with points evenly along each side, the way a
 * router draws a road.
 *
 * @param  list<array{0: float, 1: float}>  $corners
 * @return list<array{0: float, 1: float}>
 */
function lineThrough(array $corners, int $pieces = 10): array
{
    $line = [$corners[0]];

    for ($index = 1, $count = count($corners); $index < $count; $index++) {
        [$fromLon, $fromLat] = $corners[$index - 1];
        [$toLon, $toLat] = $corners[$index];

        for ($step = 1; $step <= $pieces; $step++) {
            $line[] = [$fromLon + (($toLon - $fromLon) * $step / $pieces), $fromLat + (($toLat - $fromLat) * $step / $pieces)];
        }
    }

    return $line;
}
