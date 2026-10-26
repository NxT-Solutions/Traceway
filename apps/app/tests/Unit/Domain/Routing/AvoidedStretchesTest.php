<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\AvoidedStretches;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\LandLeg;
use App\Domain\Routing\Layout;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

function stretchAt(string $id, float $longitude, float $latitude): AvoidedStretch
{
    return new AvoidedStretch($id, $latitude, $longitude, [[$longitude - 0.001, $latitude], [$longitude + 0.001, $latitude]]);
}

it('picks the stretches within reach, nearest first', function (): void {
    $picked = AvoidedStretches::nearest([
        stretchAt('far', 3.9, 51.05),
        stretchAt('near', 3.73, 51.05),
        stretchAt('nearer', 3.725, 51.05),
    ], [3.72, 51.05], 5_000);

    expect(array_map(fn (AvoidedStretch $avoidedStretch): string => $avoidedStretch->id, $picked))->toBe(['nearer', 'near']);
});

it('sends no more than a plan takes', function (): void {
    $many = array_map(fn (int $index): AvoidedStretch => stretchAt('s'.$index, 3.72 + $index * 0.0001, 51.05), range(1, 60));

    expect(AvoidedStretches::nearest($many, [3.72, 51.05], 50_000))->toHaveCount(AvoidedStretches::MOST_PER_PLAN);
});

it('keeps a short way whole', function (): void {
    $shape = [[3.72, 51.05], [3.724, 51.05]];

    expect(AvoidedStretches::clipped($shape, [3.722, 51.05]))->toBe($shape);
});

it('cuts a long way to a kilometre either side of the tap', function (): void {
    $shape = [[3.6, 51.05], [3.65, 51.05], [3.7, 51.05], [3.75, 51.05], [3.8, 51.05]];
    $clipped = AvoidedStretches::clipped($shape, [3.7, 51.05]);

    expect(Distance::along($clipped))->toEqualWithDelta(2_000.0, 5.0)
        ->and(Distance::metres($clipped[0], [3.7, 51.05]))->toEqualWithDelta(1_000.0, 5.0);
});

it('takes at most the stretches a plan sends', function (): void {
    $many = array_map(fn (int $index): AvoidedStretch => stretchAt('s'.$index, 3.72, 51.05), range(1, 41));

    expect(fn (): RouteQuery => new RouteQuery(Sport::Road, Shape::Loop, 20_000, Surface::Paved, ElevationBias::Neutral, 51.05, 3.72, avoid: $many))
        ->toThrow(InvalidArgumentException::class);
});

it('copies a plan with the stretches to skip', function (): void {
    $routeQuery = new RouteQuery(Sport::Gravel, Shape::Loop, 40_000, Surface::Mixed, ElevationBias::Maximize, 51.05, 3.72, 51.1, 3.8, preferQuiet: true);
    $avoiding = $routeQuery->withAvoid([stretchAt('a', 3.73, 51.05)]);

    expect($avoiding->avoid)->toHaveCount(1)
        ->and($avoiding->sport)->toBe(Sport::Gravel)
        ->and($avoiding->viaLatitude)->toBe(51.1)
        ->and($avoiding->preferQuiet)->toBeTrue()
        ->and($routeQuery->avoid)->toBe([]);
});

it('sends a stretch found on foot only with plans on foot', function (): void {
    $onFoot = new AvoidedStretch('path', 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]], sport: Sport::Hike, surface: Surface::Dirt);
    $onABike = new AvoidedStretch('track', 51.06, 3.722, [[3.72, 51.06], [3.724, 51.06]], sport: Sport::Gravel, surface: Surface::Mixed);

    expect(AvoidedStretches::forSport([$onFoot, $onABike], Sport::Road))->toBe([$onABike])
        ->and(AvoidedStretches::forSport([$onFoot, $onABike], Sport::Run))->toBe([$onFoot, $onABike]);
});

it('finds the middle of a way, away from its junctions', function (): void {
    expect(AvoidedStretches::middle([[3.72, 51.05], [3.721, 51.05], [3.724, 51.05]]))->toEqualWithDelta([3.722, 51.05], 0.000001);
});

it('reaches as far as a whole chain of legs', function (): void {
    $legs = [
        new LandLeg(Sport::Road, 20_000, Surface::Paved, ElevationBias::Neutral, finish: [3.8, 51.1]),
        new LandLeg(Sport::Run, 10_000, Surface::Mixed, ElevationBias::Neutral, finish: [3.85, 51.12]),
    ];

    expect(new MultisportQuery(Layout::Chain, 51.05, 3.72, $legs)->reachMetres())->toBe(30_000)
        ->and(new MultisportQuery(Layout::Loops, 51.05, 3.72, $legs)->reachMetres())->toBe(20_000);
});

it('lays a band across the middle of a road, wide enough for both carriageways', function (): void {
    $road = new AvoidedStretch('road', 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]]);
    $band = AvoidedStretches::band($road);

    expect($band)->toHaveCount(5)
        ->and($band[0])->toBe($band[4])
        // 16 m along the road, 20 m across it.
        ->and(Distance::metres($band[0], $band[1]))->toEqualWithDelta(16.0, 0.5)
        ->and(Distance::metres($band[1], $band[2]))->toEqualWithDelta(20.0, 0.5)
        ->and(Distance::metres([($band[0][0] + $band[2][0]) / 2, ($band[0][1] + $band[2][1]) / 2], [3.722, 51.05]))->toBeLessThan(1.0);
});

it('keeps the band clear of the junctions at either end', function (): void {
    $road = new AvoidedStretch('near-end', 51.05, 3.72005, [[3.72, 51.05], [3.724, 51.05]]);
    $band = AvoidedStretches::band($road);

    expect(Distance::metres([3.72, 51.05], [($band[0][0] + $band[3][0]) / 2, ($band[0][1] + $band[3][1]) / 2]))->toBeGreaterThanOrEqual(3.9);
});

it('gives a road a few metres long a band a metre long', function (): void {
    $road = new AvoidedStretch('short', 51.05, 3.72003, [[3.72, 51.05], [3.72006, 51.05]]);
    $band = AvoidedStretches::band($road);

    expect(Distance::metres($band[0], $band[1]))->toEqualWithDelta(1.0, 0.1);
});
