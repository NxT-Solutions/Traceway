<?php

declare(strict_types=1);

use App\Domain\Routing\Details\Climb;
use App\Domain\Routing\Details\ProfilePoint;

/**
 * @param  list<float>  $heights
 * @return list<ProfilePoint>
 */
function profileOf(array $heights): array
{
    return array_map(fn (float $height, int $index): ProfilePoint => new ProfilePoint($index * 30, $height), $heights, array_keys($heights));
}

it('adds up a hill and the way down', function (): void {
    $profile = profileOf([10.0, 20.0, 30.0, 40.0, 30.0, 10.0]);

    expect(Climb::ascent($profile))->toBe(30)
        ->and(Climb::descent($profile))->toBe(30);
});

it('does not turn the wobble of a flat road into a climb', function (): void {
    $profile = profileOf([10.0, 11.5, 9.8, 11.2, 10.1, 12.0, 10.4, 11.0]);

    expect(Climb::ascent($profile))->toBe(0)
        ->and(Climb::descent($profile))->toBe(0);
});

it('counts a slow climb once it passes the threshold', function (): void {
    $heights = [];

    for ($index = 0; $index <= 42; $index++) {
        $heights[] = 100.0 + $index;
    }

    expect(Climb::ascent(profileOf($heights)))->toBe(42);
});

it('has nothing to add for an empty profile', function (): void {
    expect(Climb::ascent([]))->toBe(0)->and(Climb::descent([]))->toBe(0);
});

it('averages out a spike that is a bridge, not a hill', function (): void {
    $heights = array_fill(0, 20, 10.0);
    $heights[10] = 40.0;
    $smoothed = Climb::smooth(profileOf($heights));

    expect($smoothed)->toHaveCount(20)
        ->and($smoothed[10]->distanceMetres)->toBe(300)
        ->and($smoothed[10]->elevationMetres)->toBeLessThan(17.0)
        ->and($smoothed[0]->elevationMetres)->toBe(10.0)
        ->and(Climb::ascent($smoothed))->toBeLessThan(Climb::ascent(profileOf($heights)));
});
