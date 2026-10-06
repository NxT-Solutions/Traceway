<?php

declare(strict_types=1);

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
use Tests\TestCase;

uses(TestCase::class);

it('draws a loop from a valhalla trip', function () {
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

    $route = (new ValhallaRoutingEngine($connector))->route(new RouteQuery(
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
