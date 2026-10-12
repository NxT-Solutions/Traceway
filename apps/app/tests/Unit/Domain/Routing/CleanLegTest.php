<?php

declare(strict_types=1);

use App\Domain\Routing\CleanLeg;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

covers(CleanLeg::class);

uses()->group('mutation');

/**
 * @param  list<array{0: float, 1: float}>  $coordinates
 */
function legRoute(array $coordinates, int $metres): GeneratedRoute
{
    return new GeneratedRoute(
        new RouteQuery(Sport::Run, Shape::Loop, 10_000, Surface::Paved, ElevationBias::Neutral, 51.0, 4.0),
        $coordinates,
        $metres,
        0,
    );
}

it('does not head back out on the road an earlier leg came in on', function (): void {
    $ridden = [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.0]];
    $generatedRoute = legRoute([[4.0, 51.0], [4.02, 51.02], [4.02, 51.0], [4.0, 51.0]], 10_000);
    $elsewhere = legRoute([[4.0, 51.0], [3.98, 51.0], [3.98, 50.98], [4.0, 51.0]], 8_000);

    expect(CleanLeg::doubledShare($ridden, $generatedRoute))->toBeGreaterThan(CleanLeg::DOUBLED)
        ->and(CleanLeg::doubledShare($ridden, $elsewhere))->toBe(0.0)
        ->and(CleanLeg::best($ridden, [$generatedRoute, $elsewhere], 10_000))->toBe($elsewhere);
});

it('drops a leg that turns around on its own road for a clean one further off the length', function (): void {
    $generatedRoute = legRoute([[4.0, 51.0], [4.0, 51.03], [4.0, 51.0], [4.03, 51.0]], 12_000);
    $clean = legRoute([[4.0, 51.0], [4.015, 51.02], [4.03, 51.0]], 9_000);

    expect(CleanLeg::best([], [$generatedRoute, $clean], 12_000))->toBe($clean)
        ->and(CleanLeg::best([], [], 12_000))->toBeNull();
});

it('takes the leg nearest the length when both are clean', function (): void {
    $generatedRoute = legRoute([[4.0, 51.0], [4.015, 51.02], [4.03, 51.0]], 11_500);
    $far = legRoute([[4.0, 51.0], [4.015, 50.98], [4.03, 51.0]], 8_000);

    expect(CleanLeg::best([], [$far, $generatedRoute], 12_000))->toBe($generatedRoute);
});

it('cuts the stub where a detour drives to its point and back', function (): void {
    $generatedRoute = legRoute([
        [4.0, 51.0], [4.01, 51.0], [4.02, 51.0], [4.02, 51.004], [4.02, 51.008],
        [4.02, 51.004], [4.02, 51.0], [4.03, 51.0], [4.04, 51.0],
    ], 4_000);
    $trimmed = CleanLeg::withoutSpurs($generatedRoute, [4.0, 51.0], [4.04, 51.0]);

    expect($trimmed->coordinates)->not->toContain([4.02, 51.008])
        ->and($trimmed->distanceMetres)->toBeLessThan(4_000)
        ->and($trimmed->coordinates[0])->toBe([4.0, 51.0])
        ->and(array_last($trimmed->coordinates))->toBe([4.04, 51.0]);
});
