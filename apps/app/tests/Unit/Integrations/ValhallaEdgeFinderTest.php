<?php

declare(strict_types=1);

use App\Domain\Routing\RoadEdge;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Integrations\Valhalla\Polyline;
use App\Integrations\Valhalla\Requests\LocateRequest;
use App\Integrations\Valhalla\ValhallaConnector;
use App\Integrations\Valhalla\ValhallaEdgeFinder;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

uses(TestCase::class);

function edgeFinder(MockResponse $mockResponse): ValhallaEdgeFinder
{
    $connector = new ValhallaConnector('https://valhalla.test');
    $connector->withMockClient(new MockClient([LocateRequest::class => $mockResponse]));

    return new ValhallaEdgeFinder($connector);
}

it('finds the way under a point', function (): void {
    $edge = edgeFinder(MockResponse::make([[
        'edges' => [[
            'correlated_lat' => 51.0,
            'correlated_lon' => 4.001,
            'edge_info' => [
                'shape' => Polyline::encode([[4.0, 51.0], [4.002, 51.0]]),
                'way_id' => 42,
                'names' => ['Mill Road'],
            ],
        ]],
    ]]))->near(51.0, 4.001, Sport::Road, Surface::Paved);

    expect($edge)->toBeInstanceOf(RoadEdge::class)
        ->and($edge?->wayId)->toBe(42)
        ->and($edge?->name)->toBe('Mill Road');
});

it('finds no way when the point is on none', function (): void {
    expect(edgeFinder(MockResponse::make([['edges' => []]]))->near(51.0, 4.001, Sport::Road, Surface::Paved))->toBeNull();
});

it('fails loudly when a page comes back instead of an answer, so it is not taken as no way', function (string $body): void {
    edgeFinder(MockResponse::make($body, 200, ['Content-Type' => 'text/html']))->near(51.0, 4.001, Sport::Road, Surface::Paved);
})->with([
    'a page' => '<html><body>Please wait</body></html>',
    'a bare string' => '"Please wait"',
    'nothing' => '',
])->throws(RuntimeException::class, 'The router did not find the way under the point.');
