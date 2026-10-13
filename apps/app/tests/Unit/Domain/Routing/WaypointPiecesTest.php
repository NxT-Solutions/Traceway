<?php

declare(strict_types=1);

use App\Domain\Routing\Distance;
use App\Domain\Routing\WaypointPieces;

covers(WaypointPieces::class);

uses()->group('mutation');

/**
 * @param  list<array{0: float, 1: float}>  $piece
 */
function span(array $piece): float
{
    return Distance::along($piece);
}

it('keeps a short route in one piece', function (): void {
    $waypoints = [[4.9, 51.1], [4.95, 51.15], [5.0, 51.1], [4.9, 51.1]];

    expect(WaypointPieces::split($waypoints, 140_000))->toBe([$waypoints]);
});

it('splits a long loop into pieces under the limit that join end to start', function (): void {
    $waypoints = [[4.917, 51.087], [5.3, 51.5], [5.7, 51.1], [5.3, 50.7], [4.917, 51.087]];
    $pieces = WaypointPieces::split($waypoints, 140_000);

    expect(count($pieces))->toBeGreaterThan(1)
        ->and($pieces[0][0])->toBe([4.917, 51.087])
        ->and(array_last(array_last($pieces)))->toBe([4.917, 51.087]);

    foreach ($pieces as $index => $piece) {
        expect(span($piece))->toBeLessThanOrEqual(140_000);

        if ($index > 0) {
            expect($piece[0])->toBe(array_last($pieces[$index - 1]));
        }
    }

    $kept = array_merge(...array_map(fn (array $piece): array => $piece, $pieces));

    foreach ($waypoints as $waypoint) {
        expect($kept)->toContain($waypoint);
    }
});

it('adds points along one hop longer than the limit', function (): void {
    $pieces = WaypointPieces::split([[4.0, 51.0], [7.0, 51.0]], 100_000);

    expect(count($pieces))->toBeGreaterThan(1);

    foreach ($pieces as $piece) {
        expect(span($piece))->toBeLessThanOrEqual(100_000);
    }

    expect($pieces[0][0])->toBe([4.0, 51.0])
        ->and(array_last(array_last($pieces)))->toBe([7.0, 51.0]);
});

it('has no pieces for no points', function (): void {
    expect(WaypointPieces::split([], 140_000))->toBe([]);
});

it('splits a route with more points than a server takes at once', function (): void {
    $waypoints = [];

    for ($index = 0; $index < 25; $index++) {
        $waypoints[] = [4.0 + $index * 0.001, 51.0];
    }

    $pieces = WaypointPieces::split($waypoints, 140_000, 10);

    expect(array_map(count(...), $pieces))->toBe([10, 10, 7])
        ->and($pieces[1][0])->toBe($pieces[0][9])
        ->and($pieces[2][0])->toBe($pieces[1][9])
        ->and(array_last($pieces[2]))->toBe(array_last($waypoints));
});
