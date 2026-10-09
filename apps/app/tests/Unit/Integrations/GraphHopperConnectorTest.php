<?php

declare(strict_types=1);

use App\Integrations\GraphHopper\GraphHopperConnector;
use App\Integrations\GraphHopper\Requests\CalculateRouteRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

uses(TestCase::class);

it('posts a route request to the configured graphhopper', function (): void {
    $mock = new MockClient([
        CalculateRouteRequest::class => MockResponse::make(['paths' => []], 200),
    ]);

    $connector = new GraphHopperConnector('http://graphhopper.test/');
    $connector->withMockClient($mock);

    $response = $connector->send(new CalculateRouteRequest([
        'profile' => 'bike',
        'points' => [[3.72, 51.05]],
    ]));

    expect($connector->resolveBaseUrl())->toBe('http://graphhopper.test')
        ->and($response->status())->toBe(200)
        ->and($response->json('paths'))->toBe([]);

    $mock->assertSent(fn (CalculateRouteRequest $calculateRouteRequest): bool => $calculateRouteRequest->resolveEndpoint() === '/route'
        && $calculateRouteRequest->body()->all() === [
            'profile' => 'bike',
            'points' => [[3.72, 51.05]],
        ]);
});
