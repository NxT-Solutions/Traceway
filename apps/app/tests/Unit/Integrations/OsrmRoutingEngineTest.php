<?php

declare(strict_types=1);

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
