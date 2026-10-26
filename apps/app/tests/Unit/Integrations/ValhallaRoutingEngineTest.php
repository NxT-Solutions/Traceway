<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\Distance;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\FollowPoint;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\Requests\RouteRequest;
use App\Integrations\Valhalla\Requests\TraceRouteRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaRoutingEngine;
use Saloon\Exceptions\Request\FatalRequestException;
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
        ->and($route->waypoints)->toHaveCount(5)
        ->and($route->waypoints[0])->toBe([3.72, 51.05])
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

it('follows a line through points held to the way it goes, with no rider filters to pull it off', function (): void {
    $bodies = [];
    $mock = new MockClient([
        RouteRequest::class => function (PendingRequest $pendingRequest) use (&$bodies): MockResponse {
            /** @var array{locations: list<array{lon: float, lat: float}>} $body */
            $body = $pendingRequest->body()?->all();
            $bodies[] = $body;
            $line = array_map(fn (array $location): array => [$location['lon'], $location['lat']], $body['locations']);

            return MockResponse::make(['trip' => ['summary' => ['length' => 1.0], 'legs' => [['shape' => Polyline::encode($line)]]]], 200);
        },
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $points = [];

    for ($index = 0; $index < 12; $index++) {
        $points[] = new FollowPoint([4.0 + $index * 0.01, 51.0], $index === 11 ? null : 90);
    }

    $generatedRoute = new ValhallaRoutingEngine($connector)->follow(new RouteQuery(
        sport: Sport::Gravel,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Maximize,
        startLatitude: 51.0,
        startLongitude: 4.0,
        preferQuiet: true,
    ), $points);

    $first = $bodies[0] ?? [];

    expect($bodies)->toHaveCount(2)
        ->and($first['locations'][1]['heading'] ?? null)->toBe(90)
        ->and($first['locations'][1]['heading_tolerance'] ?? null)->toBe(45)
        ->and($first['locations'][1]['type'] ?? null)->toBe('through')
        ->and($first['costing_options'] ?? null)->toBe(['bicycle' => ['bicycle_type' => 'Cross']])
        ->and(array_last($bodies[1]['locations'] ?? []))->not->toHaveKey('heading')
        ->and($generatedRoute->coordinates)->toHaveCount(12);
});

it('snaps a whole line onto the roads, and gives up on one matched in parts', function (int $legs, bool $snapped): void {
    $line = [[4.0, 51.0], [4.01, 51.0], [4.02, 51.0]];
    $mock = new MockClient([
        TraceRouteRequest::class => MockResponse::make(['trip' => [
            'summary' => ['length' => 1.4],
            'legs' => array_fill(0, $legs, ['shape' => Polyline::encode($line)]),
        ]], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $route = new ValhallaRoutingEngine($connector)->match(new RouteQuery(
        sport: Sport::Run,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    ), $line);

    expect($route instanceof GeneratedRoute)->toBe($snapped);

    if ($route instanceof GeneratedRoute) {
        expect($route->distanceMetres)->toBe(1400)
            ->and($route->coordinates)->toHaveCount(3);
    }
})->with([
    'one piece' => [1, true],
    'in parts' => [2, false],
]);

it('takes a snap that times out or answers an error page as no match, so the points are followed', function (MockResponse $mockResponse): void {
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient(new MockClient([TraceRouteRequest::class => $mockResponse]));

    expect(new ValhallaRoutingEngine($connector)->match(new RouteQuery(
        sport: Sport::Road,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    ), [[4.0, 51.0], [4.01, 51.0], [4.02, 51.0]]))->toBeNull();
})->with([
    'a timeout' => fn (): MockResponse => MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Timed out'), $pendingRequest)),
    'an error page' => fn (): MockResponse => MockResponse::make('<html>Gateway timeout</html>', 504),
]);

it('keeps the start and the finish of a loop facing their own ways', function (): void {
    $bodies = [];
    $mock = new MockClient([
        RouteRequest::class => function (PendingRequest $pendingRequest) use (&$bodies): MockResponse {
            /** @var array{locations: list<array{lon: float, lat: float}>} $body */
            $body = $pendingRequest->body()?->all();
            $bodies[] = $body;
            $line = array_map(fn (array $location): array => [$location['lon'], $location['lat']], $body['locations']);

            return MockResponse::make(['trip' => ['summary' => ['length' => 1.0], 'legs' => [['shape' => Polyline::encode($line)]]]], 200);
        },
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    new ValhallaRoutingEngine($connector)->follow(new RouteQuery(
        sport: Sport::Run,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Paved,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.0,
        startLongitude: 4.0,
    ), [
        new FollowPoint([4.0, 51.0], 90),
        new FollowPoint([4.01, 51.0], 0),
        new FollowPoint([4.01, 51.01], 270),
        new FollowPoint([4.0, 51.01], 180),
        new FollowPoint([4.0, 51.0], 45),
    ]);

    $locations = $bodies[0]['locations'] ?? [];

    expect($locations[0]['heading'] ?? null)->toBe(90)
        ->and(array_last($locations)['heading'] ?? null)->toBe(45);
});

it('leaves the avoided stretches out of a built route', function (): void {
    $mock = new MockClient([
        RouteRequest::class => MockResponse::make([
            'trip' => [
                'summary' => ['length' => 3.1],
                'legs' => [['shape' => Polyline::encode([[3.72, 51.05], [3.74, 51.06]])]],
            ],
        ], 200),
    ]);
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient($mock);

    $routeQuery = new RouteQuery(
        sport: Sport::Gravel,
        shape: Shape::PointToPoint,
        distanceMetres: 5_000,
        surface: Surface::Mixed,
        elevation: ElevationBias::Neutral,
        startLatitude: 51.05,
        startLongitude: 3.72,
        avoid: [new AvoidedStretch('bosweg', 51.0502, 3.7302, [[3.729, 51.0502], [3.731, 51.0502]])],
    );

    new ValhallaRoutingEngine($connector)->trace($routeQuery, [[3.72, 51.05], [3.73, 51.055], [3.74, 51.06]]);

    expect($mock->getLastPendingRequest()?->body()?->all()['exclude_locations'] ?? null)->toBe([['lat' => 51.0502, 'lon' => 3.7302]]);
});
