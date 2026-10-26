<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Integrations\Osrm\OsrmConnector;
use App\Integrations\Osrm\OsrmRoutingEngine;
use App\Integrations\Osrm\Requests\CyclingRouteRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

uses(TestCase::class);

it('turns a cycling response into a generated loop', function (): void {
    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient(new MockClient([
        CyclingRouteRequest::class => MockResponse::make([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 12500.4,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[3.72, 51.05], [3.8, 51.1], [3.72, 51.05]],
                ],
            ]],
        ], 200),
    ]));

    $route = new OsrmRoutingEngine($connector)->route(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 12_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ));

    expect($route->distanceMetres)->toBe(12500)
        ->and($route->coordinates)->toHaveCount(3);
});

it('asks for a foot route when the sport is on foot', function (): void {
    $mock = new MockClient([
        CyclingRouteRequest::class => MockResponse::make([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 5000,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[3.72, 51.05], [3.73, 51.06]],
                ],
            ]],
        ], 200),
    ]);

    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient($mock);

    new OsrmRoutingEngine($connector)->route(new RouteQuery(
        sport: Sport::Hike,
        shape: Shape::Loop,
        distanceMetres: 8_000,
        surface: Surface::Dirt,
        elevation: ElevationBias::Maximize,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ));

    $mock->assertSent(fn (CyclingRouteRequest $cyclingRouteRequest): bool => str_contains($cyclingRouteRequest->resolveEndpoint(), '/route/v1/foot/'));
});

/**
 * @param  list<list<array{0: float, 1: float}>>  $lines
 */
function osrmLines(array $lines): MockResponse
{
    return MockResponse::make([
        'code' => 'Ok',
        'routes' => array_map(fn (array $line): array => [
            'distance' => Distance::along($line),
            'geometry' => ['type' => 'LineString', 'coordinates' => $line],
        ], $lines),
    ], 200);
}

function avoidingBosweg(): RouteQuery
{
    return new RouteQuery(
        sport: Sport::Gravel,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Mixed,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.715,
        avoid: [new AvoidedStretch('bosweg', 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]], 'Bosweg')],
    );
}

it('takes the first alternative that keeps off an avoided stretch', function (): void {
    $along = [[3.715, 51.05], [3.73, 51.05]];
    $around = [[3.715, 51.05], [3.718, 51.052], [3.726, 51.052], [3.73, 51.05]];
    $mock = new MockClient([osrmLines([$along, $around])]);
    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient($mock);

    $generatedRoute = new OsrmRoutingEngine($connector)->trace(avoidingBosweg(), [[3.715, 51.05], [3.73, 51.05]]);

    expect($generatedRoute->coordinates)->toBe($around);
    $mock->assertSent(fn (CyclingRouteRequest $cyclingRouteRequest): bool => $cyclingRouteRequest->query()->get('alternatives') === 'true');
});

it('goes around the stretch through a point beside it when no alternative does', function (): void {
    $along = [[3.715, 51.05], [3.73, 51.05]];
    $around = [[3.715, 51.05], [3.722, 51.0514], [3.73, 51.05]];
    $mock = new MockClient([osrmLines([$along]), osrmLines([$around])]);
    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient($mock);

    $generatedRoute = new OsrmRoutingEngine($connector)->trace(avoidingBosweg(), [[3.715, 51.05], [3.73, 51.05]]);

    expect($generatedRoute->coordinates)->toBe($around)
        ->and($generatedRoute->waypoints)->toBe([[3.715, 51.05], [3.73, 51.05]]);
    $mock->assertSentCount(2);
});

it('keeps the first line when nothing keeps off the stretch', function (): void {
    $along = [[3.715, 51.05], [3.73, 51.05]];
    $mock = new MockClient([osrmLines([$along]), osrmLines([$along]), osrmLines([$along])]);
    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient($mock);

    $generatedRoute = new OsrmRoutingEngine($connector)->trace(avoidingBosweg(), [[3.715, 51.05], [3.73, 51.05]]);

    expect($generatedRoute->coordinates)->toBe($along);
    $mock->assertSentCount(3);
});

it('asks for one line when the rider avoids nothing', function (): void {
    $mock = new MockClient([osrmLines([[[3.715, 51.05], [3.73, 51.05]]])]);
    $connector = new OsrmConnector('https://router.test');
    $connector->withMockClient($mock);

    new OsrmRoutingEngine($connector)->trace(new RouteQuery(Sport::Road, Shape::PointToPoint, 5_000, Surface::Paved, ElevationBias::Neutral, 51.05, 3.715), [[3.715, 51.05], [3.73, 51.05]]);

    $mock->assertSent(fn (CyclingRouteRequest $cyclingRouteRequest): bool => $cyclingRouteRequest->query()->get('alternatives') === 'false');
});
