<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\AvoidedStretches;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
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
