<?php

declare(strict_types=1);

use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RoadSurface;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Details\SurfaceShare;
use App\Domain\Routing\Details\Turn;

function sampleDetails(): RouteDetails
{
    return new RouteDetails(
        profile: [new ProfilePoint(0, 10.0), new ProfilePoint(100, 20.0), new ProfilePoint(200, 15.0)],
        ascentMetres: 10,
        descentMetres: 5,
        surfaces: [new SurfaceShare(RoadSurface::Paved, 150), new SurfaceShare(RoadSurface::Gravel, 50)],
        cues: [new Cue(80, 4.01, 51.0, Turn::SlightRight, 'Mill Road')],
        stops: [new Stop(120, 4.012, 51.0, StopKind::Cafe, 'Corner Café')],
    );
}

it('reads back what it wrote', function (): void {
    $routeDetails = sampleDetails();
    $copy = RouteDetails::fromArray(json_decode((string) json_encode($routeDetails->toArray()), associative: true));

    expect($copy)->toEqual($routeDetails);
});

it('reads the height between two samples', function (): void {
    $routeDetails = sampleDetails();

    expect($routeDetails->elevationAt(50))->toEqualWithDelta(15.0, 0.001)
        ->and($routeDetails->elevationAt(150))->toEqualWithDelta(17.5, 0.001)
        ->and($routeDetails->elevationAt(-10))->toBe(10.0)
        ->and($routeDetails->elevationAt(500))->toBe(15.0)
        ->and(new RouteDetails([], 0, 0, [], [], [])->elevationAt(10))->toBeNull();
});

it('refuses details in a shape it does not know', function (): void {
    expect(fn (): RouteDetails => RouteDetails::fromArray(['profile' => 'flat']))->toThrow(InvalidArgumentException::class)
        ->and(fn (): RouteDetails => RouteDetails::fromArray(['profile' => [[0, 'high']], 'ascent_metres' => 0, 'descent_metres' => 0]))->toThrow(InvalidArgumentException::class);
});

it('names three surfaces from the seven the router knows', function (string $valhalla, RoadSurface $roadSurface): void {
    expect(RoadSurface::fromValhalla($valhalla))->toBe($roadSurface);
})->with([
    ['paved_smooth', RoadSurface::Paved],
    ['paved_rough', RoadSurface::Paved],
    ['compacted', RoadSurface::Gravel],
    ['gravel', RoadSurface::Gravel],
    ['dirt', RoadSurface::Dirt],
    ['path', RoadSurface::Dirt],
    ['moon_dust', RoadSurface::Unknown],
]);

it('turns a router maneuver into a turn, or nothing for a straight road', function (int $type, ?Turn $turn): void {
    expect(Turn::fromValhalla($type))->toBe($turn);
})->with([
    [10, Turn::Right],
    [15, Turn::Left],
    [9, Turn::SlightRight],
    [16, Turn::SlightLeft],
    [11, Turn::SharpRight],
    [14, Turn::SharpLeft],
    [12, Turn::UTurn],
    [26, Turn::Straight],
    [8, null],
    [1, null],
    [4, null],
]);
