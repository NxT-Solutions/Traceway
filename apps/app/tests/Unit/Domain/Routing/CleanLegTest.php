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

    expect(CleanLeg::doublesBack($ridden, $generatedRoute))->toBeTrue()
        ->and(CleanLeg::doubledMetres($ridden, $elsewhere))->toBe(0.0)
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

it('drops a long bike leg that rides 2 km back down one road, however small a share that is', function (): void {
    // A 40 km square from the transition, with a 2 km road out of its north side and back.
    $generatedRoute = legRoute([
        [4.0, 51.0], [4.0715, 51.0], [4.143, 51.0], [4.143, 51.045], [4.143, 51.09],
        [4.07, 51.09], [4.07, 51.099], [4.07, 51.108], [4.07, 51.099], [4.07, 51.09],
        [4.0, 51.09], [4.0, 51.045], [4.0, 51.0],
    ], 44_000);
    $clean = legRoute([
        [4.0, 51.0], [4.0715, 51.0], [4.143, 51.0], [4.143, 51.045], [4.143, 51.09],
        [4.07, 51.09], [4.0, 51.09], [4.0, 51.045], [4.0, 51.0],
    ], 40_000);

    expect(CleanLeg::doublesBack([], $generatedRoute))->toBeTrue()
        ->and(CleanLeg::best([], [$generatedRoute, $clean], 44_000))->toBe($clean);
});

it('gives a handover leg no ride home, since it ends somewhere else', function (): void {
    // 5 km east, then 1.2 km back west to the handover.
    $generatedRoute = legRoute([[4.0, 51.0], [4.036, 51.0], [4.0715, 51.0], [4.0629, 51.0], [4.0544, 51.0]], 6_200);

    expect(CleanLeg::doubledMetres([], $generatedRoute))->toBeGreaterThan(1_000.0)
        ->and(CleanLeg::doublesBack([], $generatedRoute))->toBeTrue();
});

it('lets every leg ride the street of the transition both ways', function (): void {
    // The run leaves north up the 330 m transition street and comes home down it. The bike
    // goes up it after the run came down, loops west, and comes home down it again.
    $run = [[4.0, 51.0], [4.0, 51.003], [4.03, 51.003], [4.03, 51.03], [4.0, 51.03], [4.0, 51.003], [4.0, 51.0]];
    $generatedRoute = legRoute([[4.0, 51.0], [4.0, 51.003], [3.95, 51.003], [3.95, 51.05], [3.99, 51.05], [3.99, 51.003], [4.0, 51.003], [4.0, 51.0]], 15_000);

    expect(CleanLeg::doubledMetres($run, $generatedRoute))->toBe(0.0)
        ->and(CleanLeg::doublesBack($run, $generatedRoute))->toBeFalse();
});

it('keeps a short run clean rather than closer to its length with a stretch back down a road', function (): void {
    // A 6 km square, and the same square with a 600 m road out of its north side and back.
    $generatedRoute = legRoute([
        [4.0, 51.0], [4.0214, 51.0], [4.0214, 51.0135], [4.0107, 51.0135],
        [4.0107, 51.0189], [4.0107, 51.0135], [4.0, 51.0135], [4.0, 51.0],
    ], 7_200);
    $clean = legRoute([[4.0, 51.0], [4.0214, 51.0], [4.0214, 51.0135], [4.0107, 51.0135], [4.0, 51.0135], [4.0, 51.0]], 6_700);

    expect(CleanLeg::doublesBack([], $generatedRoute))->toBeFalse()
        ->and(CleanLeg::best([], [$generatedRoute, $clean], 10_000))->toBe($clean);
});

it('takes the leg with the least road ridden twice when every leg turns around', function (): void {
    // Both ride back down the road out of a 6 km square: 1.2 km, or 2 km nearer the length.
    $generatedRoute = legRoute([
        [4.0, 51.0], [4.0214, 51.0], [4.0214, 51.0135], [4.0107, 51.0135],
        [4.0107, 51.0243], [4.0107, 51.0135], [4.0, 51.0135], [4.0, 51.0],
    ], 8_400);
    $more = legRoute([
        [4.0, 51.0], [4.0214, 51.0], [4.0214, 51.0135], [4.0107, 51.0135],
        [4.0107, 51.0315], [4.0107, 51.0135], [4.0, 51.0135], [4.0, 51.0],
    ], 10_000);

    expect(CleanLeg::doublesBack([], $generatedRoute))->toBeTrue()
        ->and(CleanLeg::doublesBack([], $more))->toBeTrue()
        ->and(CleanLeg::best([], [$more, $generatedRoute], 10_000))->toBe($generatedRoute);
});

it('lets a chain leg come home down the road the first leg left the transition on', function (): void {
    // The bike leaves the transition 1.5 km up a road to the north, then 7 km east to the handover.
    // The run goes north, west, and comes home down that same road to the transition.
    $bike = [[4.0, 51.0], [4.0, 51.0068], [4.0, 51.0135], [4.1, 51.0135]];
    $generatedRoute = legRoute([[4.1, 51.0135], [4.1, 51.05], [4.0, 51.05], [4.0, 51.0135], [4.0, 51.0068], [4.0, 51.0]], 16_000);

    expect(CleanLeg::doubledMetres($bike, $generatedRoute))->toBeLessThan(10.0)
        ->and(CleanLeg::doublesBack($bike, $generatedRoute))->toBeFalse();
});
