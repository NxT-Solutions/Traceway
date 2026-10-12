<?php

declare(strict_types=1);

use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\Requests\RouteRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

it('draws a loop from a valhalla trip', function (): void {
    $shape = Polyline::encode([
        [3.72, 51.05],
        [3.8, 51.08],
        [3.72, 51.05],
    ]);

    $mock = new MockClient([
        RouteRequest::class => MockResponse::make([
            'trip' => [
                'summary' => ['length' => 19.4],
                'legs' => [['shape' => $shape]],
            ],
        ], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $route = new ValhallaRoutingEngine($connector)->route(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 20_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Minimize,
        startLatitude: 51.05,
        startLongitude: 3.72,
    ));

    expect($route->distanceMetres)->toBe(19400)
        ->and($route->coordinates[0][0])->toEqualWithDelta(3.72, 0.00001)
        ->and($mock->getRecordedResponses())->toHaveCount(6);
});

it('asks for a loop longer than one request in pieces and joins them', function (): void {
    $mock = new MockClient([
        RouteRequest::class => function (PendingRequest $pendingRequest): MockResponse {
            /** @var array{locations: list<array{lon: float, lat: float}>} $body */
            $body = $pendingRequest->body()?->all();
            $line = array_map(fn (array $location): array => [$location['lon'], $location['lat']], $body['locations']);

            return MockResponse::make([
                'trip' => [
                    'summary' => ['length' => 60.0],
                    'legs' => [['shape' => Polyline::encode($line)]],
                ],
            ], 200);
        },
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $routes = new ValhallaRoutingEngine($connector, 100_000)->candidates(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::Loop,
        distanceMetres: 180_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.087,
        startLongitude: 4.917,
    ));
    $requests = count($mock->getRecordedResponses());

    expect($requests)->toBeGreaterThan(6)
        ->and($routes)->not->toBeEmpty()
        ->and($routes[0]->coordinates[0][0])->toEqualWithDelta(4.917, 0.00001)
        ->and(array_last($routes[0]->coordinates)[0])->toEqualWithDelta(4.917, 0.00001);

    foreach ($mock->getRecordedResponses() as $response) {
        /** @var array{locations: list<array{lon: float, lat: float}>} $sent */
        $sent = $response->getPendingRequest()->body()?->all();
        $points = array_map(fn (array $location): array => [$location['lon'], $location['lat']], $sent['locations']);

        expect(Distance::along($points))->toBeLessThanOrEqual(100_000);
    }
});
