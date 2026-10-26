<?php

declare(strict_types=1);

use App\Domain\Routing\LegSplitter;

it('cuts a line at each point it was planned through', function (): void {
    $line = [[3.70, 51.05], [3.71, 51.05], [3.72, 51.05], [3.73, 51.05], [3.74, 51.05]];

    expect(LegSplitter::cuts($line, [[3.70, 51.05], [3.72, 51.0501], [3.74, 51.05]]))->toBe([0, 2, 4])
        ->and(LegSplitter::legs($line, [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05]]))->toBe([
            [[3.70, 51.05], [3.71, 51.05], [3.72, 51.05]],
            [[3.72, 51.05], [3.73, 51.05], [3.74, 51.05]],
        ]);
});

it('takes the first close pass of a loop, not a later one', function (): void {
    // Out east and back: the line passes 3.72 going out and again coming home.
    $line = [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05], [3.74, 51.06], [3.72, 51.0501], [3.70, 51.05]];

    expect(LegSplitter::cuts($line, [[3.70, 51.05], [3.72, 51.0501], [3.74, 51.06], [3.70, 51.05]]))->toBe([0, 1, 3, 5]);
});

it('never goes back along the line', function (): void {
    $line = [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05], [3.72, 51.0502], [3.70, 51.05]];

    expect(LegSplitter::cuts($line, [[3.70, 51.05], [3.74, 51.05], [3.72, 51.05], [3.70, 51.05]]))->toBe([0, 2, 3, 4]);
});

it('measures each leg', function (): void {
    $legs = LegSplitter::metres([[3.70, 51.05], [3.71, 51.05], [3.72, 51.05]], [[3.70, 51.05], [3.71, 51.05], [3.72, 51.05]]);

    expect($legs)->toHaveCount(2)
        ->and($legs[0])->toEqualWithDelta(700, 2)
        ->and($legs[1])->toEqualWithDelta(700, 2);
});

it('cuts at the nearest vertex of a pass, not the first one in reach', function (): void {
    // The line comes in 25 m north of the point, passes 5 m from it, and leaves 20 m south.
    $line = [[3.70, 51.05], [3.7198, 51.050225], [3.72, 51.050045], [3.7202, 51.04982], [3.74, 51.05]];

    expect(LegSplitter::cuts($line, [[3.70, 51.05], [3.72, 51.05], [3.74, 51.05]]))->toBe([0, 2, 4]);
});
