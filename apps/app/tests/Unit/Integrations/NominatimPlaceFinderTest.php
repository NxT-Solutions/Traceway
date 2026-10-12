<?php

declare(strict_types=1);

use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceFinder;
use App\Integrations\Nominatim\Requests\SearchRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function nominatim(MockResponse $mockResponse): NominatimPlaceFinder
{
    $connector = new NominatimConnector('https://nominatim.test');
    $connector->withMockClient(new MockClient([SearchRequest::class => $mockResponse]));

    return new NominatimPlaceFinder($connector);
}

it('finds the best match for a place name', function (): void {
    $mock = new MockClient([
        SearchRequest::class => MockResponse::make([
            ['lat' => '51.0907', 'lon' => '4.9166', 'display_name' => 'Westerlo, Turnhout, Antwerp, Belgium'],
        ]),
    ]);
    $connector = new NominatimConnector('https://nominatim.test');
    $connector->withMockClient($mock);

    $place = new NominatimPlaceFinder($connector)->find('Westerlo, Belgium');

    expect($place?->name)->toBe('Westerlo, Turnhout, Antwerp, Belgium')
        ->and($place?->latitude)->toBe(51.0907)
        ->and($place?->longitude)->toBe(4.9166);

    $mock->assertSent(fn (SearchRequest $searchRequest, Response $response): bool => $response->getPendingRequest()->query()->get('q') === 'Westerlo, Belgium'
        && $response->getPendingRequest()->query()->get('limit') === 1
        && str_starts_with((string) $response->getPendingRequest()->headers()->get('User-Agent'), 'Traceway/'));
});

it('finds nothing when the map knows no such place', function (): void {
    expect(nominatim(MockResponse::make([]))->find('Atlantis'))->toBeNull();
});

it('fails loudly when the place search is down', function (): void {
    nominatim(MockResponse::make(['error' => 'busy'], 503))->find('Westerlo');
})->throws(RuntimeException::class, 'The place search did not answer.');
