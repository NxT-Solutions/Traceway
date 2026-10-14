<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceKind;
use App\Domain\Routing\PlaceMatch;
use App\Integrations\Nominatim\NominatimConnector;
use App\Integrations\Nominatim\NominatimPlaceSearch;
use App\Integrations\Nominatim\Requests\SearchRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;

uses(TestCase::class);

function nominatimSearch(MockClient $mockClient): NominatimPlaceSearch
{
    $connector = new NominatimConnector('https://nominatim.test');
    $connector->withMockClient($mockClient);

    return new NominatimPlaceSearch($connector);
}

it('lists places by a short name and the area that tells them apart', function (): void {
    $mock = new MockClient([
        SearchRequest::class => MockResponse::make([
            [
                'lat' => '51.0907', 'lon' => '4.9166', 'name' => 'Westerlo', 'addresstype' => 'town',
                'display_name' => 'Westerlo, Turnhout, Antwerp, Flanders, Belgium',
                'address' => ['town' => 'Westerlo', 'county' => 'Turnhout', 'state' => 'Antwerp', 'country' => 'Belgium'],
            ],
            [
                'lat' => '51.1', 'lon' => '4.95', 'name' => 'Oevel', 'addresstype' => 'village',
                'display_name' => 'Oevel, Westerlo, Turnhout, Antwerp, Belgium',
                'address' => ['village' => 'Oevel', 'town' => 'Westerlo', 'country' => 'Belgium'],
            ],
            ['lat' => 'north', 'lon' => '4.9', 'name' => 'Nowhere'],
        ]),
    ]);

    $matches = nominatimSearch($mock)->search('westerlo', 51.1, 4.9, 'nl');

    expect($matches)->toEqual([
        new PlaceMatch('Westerlo', 'Turnhout, Belgium', 51.0907, 4.9166, PlaceKind::Town),
        new PlaceMatch('Oevel', 'Westerlo, Belgium', 51.1, 4.95, PlaceKind::Town),
    ]);
    $mock->assertSent(fn (SearchRequest $searchRequest, Response $response): bool => $response->getPendingRequest()->query()->get('q') === 'westerlo'
        && $response->getPendingRequest()->query()->get('limit') === 5
        && $response->getPendingRequest()->query()->get('viewbox') === '4.4000,50.6000,5.4000,51.6000'
        && $response->getPendingRequest()->query()->get('bounded') === 0
        && $response->getPendingRequest()->query()->get('accept-language') === 'nl'
        && str_starts_with((string) $response->getPendingRequest()->headers()->get('User-Agent'), 'Traceway/'));
});

it('names a place from its address line when the map gives it no name', function (): void {
    $matches = nominatimSearch(new MockClient([
        SearchRequest::class => MockResponse::make([
            ['lat' => '51.0', 'lon' => '4.0', 'name' => '', 'display_name' => 'Grote Markt, Brussels, Belgium'],
        ]),
    ]))->search('grote markt', 51.0, 4.0);

    expect($matches)->toEqual([new PlaceMatch('Grote Markt', 'Brussels, Belgium', 51.0, 4.0)]);
});

it('names a house by its street and number, not by the number alone', function (): void {
    $matches = nominatimSearch(new MockClient([
        SearchRequest::class => MockResponse::make([
            [
                'lat' => '51.17', 'lon' => '4.35', 'name' => '', 'addresstype' => 'place', 'category' => 'place', 'type' => 'house',
                'display_name' => '3, Roodborstlaan, Hoboken, Antwerpen, Vlaanderen, België / Belgique / Belgien',
                'address' => ['house_number' => '3', 'road' => 'Roodborstlaan', 'suburb' => 'Hoboken', 'city' => 'Antwerpen', 'country' => 'Belgium'],
            ],
            [
                'lat' => '51.18', 'lon' => '4.36', 'name' => 'Roodborstlaan', 'category' => 'highway', 'type' => 'residential', 'addresstype' => 'road',
                'display_name' => 'Roodborstlaan, Hoboken, Antwerpen, Belgium',
                'address' => ['road' => 'Roodborstlaan', 'suburb' => 'Hoboken', 'city' => 'Antwerpen', 'country' => 'Belgium'],
            ],
        ]),
    ]))->search('roodborstlaan 3 hoboken', 51.1, 4.9);

    expect($matches)->toEqual([
        new PlaceMatch('Roodborstlaan 3', 'Hoboken, Belgium', 51.17, 4.35, PlaceKind::Address),
        new PlaceMatch('Roodborstlaan', 'Hoboken, Belgium', 51.18, 4.36, PlaceKind::Street),
    ]);
});

it('keeps the box around the map on the globe', function (): void {
    $mock = new MockClient([SearchRequest::class => MockResponse::make([])]);

    expect(nominatimSearch($mock)->search('dateline', 89.8, 179.9))->toBe([]);
    $mock->assertSent(fn (SearchRequest $searchRequest, Response $response): bool => $response->getPendingRequest()->query()->get('viewbox') === '179.4000,89.3000,180.0000,90.0000');
});

it('fails loudly when the place search is down', function (): void {
    nominatimSearch(new MockClient([SearchRequest::class => MockResponse::make(['error' => 'busy'], 503)]))->search('Westerlo', 51.1, 4.9);
})->throws(RuntimeException::class, 'The place search did not answer.');
