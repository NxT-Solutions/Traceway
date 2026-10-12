<?php

declare(strict_types=1);

use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\LandLeg;
use App\Domain\Routing\Layout;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Domain\Routing\SwimLeg;

covers(MultisportQuery::class, LandLeg::class, SwimLeg::class);

uses()->group('mutation');

function landLeg(Sport $sport = Sport::Road, int $distanceMetres = 20_000, ?array $finish = null): LandLeg
{
    /** @var array{0: float, 1: float}|null $finish */
    return new LandLeg($sport, $distanceMetres, Surface::Paved, ElevationBias::Neutral, finish: $finish);
}

it('keeps a brick that loops from the transition', function (): void {
    $query = new MultisportQuery(Layout::Loops, 51.05, 3.72, [landLeg(), landLeg(Sport::Run, 5_000)]);

    expect($query->transition())->toBe([3.72, 51.05])
        ->and($query->legs)->toHaveCount(2)
        ->and($query->swim)->toBeNull();
});

it('keeps a chain when every leg has a finish', function (): void {
    $query = new MultisportQuery(Layout::Chain, 51.05, 3.72, [
        landLeg(finish: [3.8, 51.1]),
        landLeg(Sport::Run, 5_000, [3.85, 51.12]),
    ]);

    expect($query->legs[1]->finish)->toBe([3.85, 51.12]);
});

it('refuses a plan with no land legs or too many', function (int $count): void {
    expect(fn (): MultisportQuery => new MultisportQuery(Layout::Loops, 51.05, 3.72, array_fill(0, $count, landLeg())))
        ->toThrow(InvalidArgumentException::class, '1 to 4 land legs');
})->with([
    'none' => [0],
    'five' => [5],
]);

it('takes up to four land legs', function (): void {
    expect(new MultisportQuery(Layout::Loops, 51.05, 3.72, array_fill(0, 4, landLeg()))->legs)->toHaveCount(4);
});

it('refuses a chained leg without a finish', function (): void {
    expect(fn (): MultisportQuery => new MultisportQuery(Layout::Chain, 51.05, 3.72, [landLeg(finish: [3.8, 51.1]), landLeg()]))
        ->toThrow(InvalidArgumentException::class, 'needs a finish');
});

it('refuses a transition off the map', function (float $latitude, float $longitude): void {
    expect(fn (): MultisportQuery => new MultisportQuery(Layout::Loops, $latitude, $longitude, [landLeg()]))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'north' => [90.1, 0.0],
    'south' => [-90.1, 0.0],
    'east' => [0.0, 180.1],
    'west' => [0.0, -180.1],
]);

it('keeps a transition on the edge of the map', function (float $latitude, float $longitude): void {
    expect(new MultisportQuery(Layout::Loops, $latitude, $longitude, [landLeg()])->transition())->toBe([$longitude, $latitude]);
})->with([
    'north east' => [90.0, 180.0],
    'south west' => [-90.0, -180.0],
]);

it('refuses a land leg outside 1 to 300 km', function (int $distanceMetres): void {
    expect(fn (): LandLeg => landLeg(distanceMetres: $distanceMetres))->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => [999],
    'too long' => [300_001],
]);

it('keeps a land leg at either end of the range', function (int $distanceMetres): void {
    expect(landLeg(distanceMetres: $distanceMetres)->distanceMetres)->toBe($distanceMetres);
})->with([
    'shortest' => [1_000],
    'longest' => [300_000],
]);

it('refuses a leg finish off the map', function (array $finish): void {
    expect(fn (): LandLeg => landLeg(finish: $finish))->toThrow(InvalidArgumentException::class);
})->with([
    'east' => [[180.1, 0.0]],
    'west' => [[-180.1, 0.0]],
    'north' => [[0.0, 90.1]],
    'south' => [[0.0, -90.1]],
]);

it('keeps a leg finish on the edge of the map', function (array $finish): void {
    expect(landLeg(finish: $finish)->finish)->toBe($finish);
})->with([
    'north east' => [[180.0, 90.0]],
    'south west' => [[-180.0, -90.0]],
]);

it('measures a drawn swim and treats a pin as no distance', function (): void {
    expect(new SwimLeg([[3.72, 51.05], [3.72, 51.06]])->distanceMetres())->toBe(1_112)
        ->and(new SwimLeg([[3.72, 51.05]])->distanceMetres())->toBe(0);
});

it('refuses a swim with no points, too many, or a point off the map', function (array $coordinates): void {
    expect(fn (): SwimLeg => new SwimLeg($coordinates))->toThrow(InvalidArgumentException::class);
})->with([
    'none' => [[]],
    'twenty one' => [array_fill(0, 21, [3.72, 51.05])],
    'east' => [[[180.1, 0.0]]],
    'west' => [[[-180.1, 0.0]]],
    'north' => [[[0.0, 90.1]]],
    'south' => [[[0.0, -90.1]]],
]);

it('keeps a swim of twenty points on the edge of the map', function (): void {
    expect((new SwimLeg(array_fill(0, 20, [180.0, 90.0])))->coordinates)->toHaveCount(20)
        ->and((new SwimLeg([[-180.0, -90.0]]))->coordinates)->toBe([[-180.0, -90.0]]);
});
