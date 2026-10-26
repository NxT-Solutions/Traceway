<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LoopClosure;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

it('sends the ride home off the road it arrived on', function (): void {
    $ridden = [
        [4.0, 51.0],
        [4.0, 51.01],
        [4.0, 51.02],
    ];
    $plans = LoopClosure::plans($ridden, [4.0, 51.02], [4.0, 51.0]);

    $short = LoopClosure::plans($ridden, [4.0, 51.02], [4.0, 51.0], 8_000);
    $long = LoopClosure::plans($ridden, [4.0, 51.02], [4.0, 51.0], 40_000);
    $shortVia = $short[0]['waypoints'][1] ?? null;
    $longVia = $long[0]['waypoints'][1] ?? null;

    expect($shortVia)->not->toBeNull()
        ->and($longVia)->not->toBeNull()
        ->and(abs(($longVia[1] ?? 0) - 51.02))->toBeGreaterThan(abs(($shortVia[1] ?? 0) - 51.02));

    expect($plans)->not->toBeEmpty();

    foreach ($plans as $plan) {
        $turn = abs($plan['heading'] - 180);
        $turn = min($turn, 360 - $turn);

        expect($turn)->toBeGreaterThan(40);
    }
});

it('keeps the closing line that does not ride back over the way out', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 10_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    $ridden = [
        [4.0, 51.0],
        [4.0, 51.01],
        [4.0, 51.02],
    ];
    $retrace = new GeneratedRoute($query, [
        [4.0, 51.02],
        [4.0, 51.01],
        [4.0, 51.0],
    ], 2_000, 0);
    $around = new GeneratedRoute($query, [
        [4.0, 51.02],
        [4.01, 51.02],
        [4.01, 51.0],
        [4.0, 51.0],
    ], 4_000, 0);

    $chosen = LoopClosure::cleanest($ridden, [$retrace, $around]);
    $combined = array_merge($ridden, array_slice($chosen?->coordinates ?? [], 1));

    expect($chosen?->coordinates[1])->toBe([4.01, 51.02])
        ->and(RouteCleanliness::reverseShare($combined))->toBeLessThan(0.12);
});

it('keeps the ride home that brings the whole route near the requested length', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
        preferQuiet: true,
    );
    $ridden = [
        [4.0, 51.0],
        [4.0, 51.02],
    ];
    $short = new GeneratedRoute($query, [
        [4.0, 51.02],
        [4.02, 51.01],
        [4.0, 51.0],
    ], 4_000, 0);
    $fitting = new GeneratedRoute($query, [
        [4.0, 51.02],
        [4.12, 51.08],
        [4.02, 51.0],
        [4.0, 51.0],
    ], 16_000, 0);

    $chosen = LoopClosure::cleanest($ridden, [$short, $fitting], 20_000);

    expect($chosen?->coordinates[1])->toBe([4.12, 51.08]);
});

it('prefers the return closest to the requested length when one is too long', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 20_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    $ridden = [
        [4.0, 51.0],
        [4.0, 51.01],
    ];
    $short = new GeneratedRoute($query, [
        [4.0, 51.01],
        [4.02, 51.005],
        [4.0, 51.0],
    ], 3_000, 0);
    $close = new GeneratedRoute($query, [
        [4.0, 51.01],
        [4.1, 51.08],
        [4.02, 51.0],
        [4.0, 51.0],
    ], 18_000, 0);
    $far = new GeneratedRoute($query, [
        [4.0, 51.01],
        [4.25, 51.15],
        [4.05, 51.0],
        [4.0, 51.0],
    ], 40_000, 0);

    $chosen = LoopClosure::cleanest($ridden, [$short, $far, $close], 20_000);

    expect($chosen?->coordinates[1])->toBe([4.1, 51.08]);
});

it('keeps the clean way home when the ride out already doubles back', function (): void {
    $query = new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 30_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    );
    // 4 km north, a 1.3 km dead end the rider asked to reach and back, 4 km more north, then 7 km east.
    $ridden = [
        [4.0, 51.0],
        [4.0, 51.018],
        [4.0, 51.036],
        [4.0186, 51.036],
        [4.0, 51.036],
        [4.0, 51.054],
        [4.0, 51.072],
        [4.05, 51.072],
        [4.1, 51.072],
    ];
    $back = new GeneratedRoute($query, [
        [4.1, 51.072],
        [4.05, 51.072],
        [4.0, 51.072],
        [4.0, 51.054],
        [4.0, 51.036],
        [4.0, 51.018],
        [4.0, 51.0],
    ], 15_000, 0);
    $around = new GeneratedRoute($query, [
        [4.1, 51.072],
        [4.1, 51.036],
        [4.1, 51.0],
        [4.05, 51.0],
        [4.0, 51.0],
    ], 15_000, 0);

    expect(RouteCleanliness::doubledMetres($ridden))->toBeGreaterThan(RouteCleanliness::DOUBLED_METRES)
        ->and(LoopClosure::cleanest($ridden, [$back, $around])?->coordinates[1] ?? null)->toBe($around->coordinates[1]);
});
