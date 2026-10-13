<?php

declare(strict_types=1);

use App\Domain\Routing\Distance;
use App\Domain\Routing\LineDistance;

it('measures each point from the start', function (): void {
    $line = [[4.0, 51.0], [4.01, 51.0], [4.02, 51.0]];
    $lineDistance = LineDistance::of($line);

    expect($lineDistance->cumulative[0])->toBe(0.0)
        ->and($lineDistance->totalMetres())->toEqualWithDelta(Distance::along($line), 0.001)
        ->and($lineDistance->cumulative[1])->toEqualWithDelta($lineDistance->totalMetres() / 2, 1.0);
});

it('finds the point after so many metres', function (): void {
    $lineDistance = LineDistance::of([[4.0, 51.0], [4.02, 51.0]]);
    $middle = $lineDistance->pointAt($lineDistance->totalMetres() / 2);

    expect($middle[0])->toEqualWithDelta(4.01, 0.0001)
        ->and($lineDistance->pointAt(-5)[0])->toBe(4.0)
        ->and($lineDistance->pointAt(1_000_000)[0])->toBe(4.02);
});

it('places a nearby point on the line and says how far off it is', function (): void {
    $lineDistance = LineDistance::of([[4.0, 51.0], [4.02, 51.0]]);
    $placed = $lineDistance->project([4.01, 51.0005]);

    expect($placed['metres'])->toEqualWithDelta($lineDistance->totalMetres() / 2, 2.0)
        ->and($placed['gap'])->toEqualWithDelta(55.7, 1.0)
        ->and($placed['point'][1])->toEqualWithDelta(51.0, 0.00001);
});

it('matches a road ridden twice on the pass near the expected distance', function (): void {
    $lineDistance = LineDistance::of([[4.0, 51.0], [4.02, 51.0], [4.0, 51.0]]);
    $total = $lineDistance->totalMetres();

    $outbound = $lineDistance->project([4.005, 51.0]);
    $homeward = $lineDistance->project([4.005, 51.0], $total * 0.85, 500);

    expect($outbound['metres'])->toBeLessThan($total / 2)
        ->and($homeward['metres'])->toBeGreaterThan($total / 2);
});

it('refuses a line of one point', function (): void {
    expect(fn (): LineDistance => LineDistance::of([[4.0, 51.0]]))->toThrow(InvalidArgumentException::class);
});
