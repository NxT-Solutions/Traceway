<?php

declare(strict_types=1);

use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceNamer;
use App\Integrations\Nominatim\Requests\ReverseRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function placeNamer(MockClient $mockClient): NominatimPlaceNamer
{
    $connector = new NominatimConnector('https://nominatim.test');
    $connector->withMockClient($mockClient);

    return new NominatimPlaceNamer($connector);
}

it('names the village a point is in', function (): void {
    $mock = new MockClient([
        ReverseRequest::class => MockResponse::make([
            'name' => 'Oevel',
            'address' => ['village' => 'Oevel', 'town' => 'Westerlo', 'country' => 'Belgium'],
        ]),
    ]);

    expect(placeNamer($mock)->nameAt(51.1, 4.9))->toBe('Oevel');

    $mock->assertSent(fn (ReverseRequest $reverseRequest, Response $response): bool => $response->getPendingRequest()->query()->get('lat') === 51.1
        && $response->getPendingRequest()->query()->get('lon') === 4.9
        && $response->getPendingRequest()->query()->get('zoom') === 14
        && str_starts_with((string) $response->getPendingRequest()->headers()->get('User-Agent'), 'Traceway/'));
});

it('names the town when the point is in no village', function (): void {
    $mock = new MockClient([
        ReverseRequest::class => MockResponse::make(['address' => ['town' => 'Westerlo', 'county' => 'Turnhout']]),
    ]);

    expect(placeNamer($mock)->nameAt(51.09, 4.92))->toBe('Westerlo');
});

it('has no name for a point out at sea', function (): void {
    $mock = new MockClient([
        ReverseRequest::class => MockResponse::make(['error' => 'Unable to geocode']),
    ]);

    expect(placeNamer($mock)->nameAt(52.0, 2.5))->toBeNull();
});

it('fails loudly when the place search is down', function (): void {
    placeNamer(new MockClient([ReverseRequest::class => MockResponse::make(['error' => 'busy'], 503)]))->nameAt(51.1, 4.9);
})->throws(RuntimeException::class, 'The place search did not answer.');
