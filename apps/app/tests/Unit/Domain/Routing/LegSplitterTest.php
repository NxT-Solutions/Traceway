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
