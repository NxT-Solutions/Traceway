<?php

declare(strict_types=1);

use App\Domain\Routing\LoopWaypoints;

it('closes a loop around the start pin', function (): void {
    $points = LoopWaypoints::around(51.05, 3.72, 40_000);

    expect($points)->toHaveCount(5)
        ->and($points[0])->toBe($points[4])
        ->and($points[1])->not->toBe($points[0]);
});

it('pulls a loop through a via point', function (): void {
    $points = LoopWaypoints::around(51.05, 3.72, 20_000, 0.0, 51.08, 3.74);

    expect($points)->toHaveCount(5)
        ->and($points[0])->toBe($points[4])
        ->and($points)->toContain([3.74, 51.08]);
});

it('builds an out and back that returns to the start', function (): void {
    $points = LoopWaypoints::outAndBack(51.05, 3.72, 20_000);

    expect($points)->toHaveCount(3)
        ->and($points[0])->toBe($points[2]);
});
