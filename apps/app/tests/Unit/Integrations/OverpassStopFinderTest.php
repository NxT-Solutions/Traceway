<?php

declare(strict_types=1);

use App\Domain\Routing\Details\FoundStop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Sport;
use App\Integrations\Overpass\OverpassConnector;
use App\Integrations\Overpass\OverpassStopFinder;
use App\Integrations\Overpass\Requests\StopsAlongRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\TestCase;

uses(TestCase::class);

it('finds water, toilets, and cafés along a line', function (): void {
    $query = '';
    $mock = new MockClient([
        StopsAlongRequest::class => function (PendingRequest $pendingRequest) use (&$query): MockResponse {
            $data = $pendingRequest->body()?->all()['data'] ?? '';
            $query = is_string($data) ? $data : '';

            return MockResponse::make(['elements' => [
                ['type' => 'node', 'lat' => 51.0, 'lon' => 4.01, 'tags' => ['amenity' => 'drinking_water']],
                ['type' => 'node', 'lat' => 51.0, 'lon' => 4.02, 'tags' => ['amenity' => 'cafe', 'name' => 'Corner Café']],
                ['type' => 'node', 'lat' => 51.0, 'lon' => 4.03, 'tags' => ['amenity' => 'toilets', 'name' => '']],
                ['type' => 'node', 'lat' => 51.0, 'lon' => 4.04, 'tags' => ['amenity' => 'bench']],
                ['type' => 'node', 'tags' => ['amenity' => 'cafe']],
            ]], 200);
        },
    ]);
    $connector = new OverpassConnector('https://overpass.test/api');
    $connector->withMockClient($mock);

    $stops = new OverpassStopFinder($connector)->along(Sport::Road, [[4.0, 51.0], [4.05, 51.0], [4.1, 51.0]]);

    expect(array_map(fn (FoundStop $foundStop): StopKind => $foundStop->kind, $stops))->toBe([StopKind::Water, StopKind::Cafe, StopKind::Toilet])
        ->and($stops[1]->name)->toBe('Corner Café')
        ->and($stops[2]->name)->toBeNull()
        ->and($query)->toContain('drinking_water|water_point|toilets|cafe')
        ->and(substr_count($query, 'node['))->toBe(2)
        ->and($query)->toContain('(50.99865,');
});

it('searches closer to the line on foot', function (): void {
    $query = '';
    $mock = new MockClient([
        StopsAlongRequest::class => function (PendingRequest $pendingRequest) use (&$query): MockResponse {
            $data = $pendingRequest->body()?->all()['data'] ?? '';
            $query = is_string($data) ? $data : '';

            return MockResponse::make(['elements' => []], 200);
        },
    ]);
    $connector = new OverpassConnector('https://overpass.test/api');
    $connector->withMockClient($mock);

    new OverpassStopFinder($connector)->along(Sport::Run, [[4.0, 51.0], [4.05, 51.0]]);

    // 75 metres is about 0.00067 degrees of latitude.
    expect($query)->toContain('(50.99933,');
});

it('fails when the server is busy', function (): void {
    $mock = new MockClient([StopsAlongRequest::class => MockResponse::make('Too busy', 429)]);
    $connector = new OverpassConnector('https://overpass.test/api');
    $connector->withMockClient($mock);

    expect(fn (): array => new OverpassStopFinder($connector)->along(Sport::Road, [[4.0, 51.0], [4.05, 51.0]]))
        ->toThrow(RuntimeException::class);
});

it('fails when the query ran out of time, rather than finding no stops', function (): void {
    $mock = new MockClient([StopsAlongRequest::class => MockResponse::make([
        'remark' => 'runtime error: Query timed out in "query" at line 1 after 10 seconds.',
        'elements' => [],
    ], 200)]);
    $connector = new OverpassConnector('https://overpass.test/api');
    $connector->withMockClient($mock);

    expect(fn (): array => new OverpassStopFinder($connector)->along(Sport::Road, [[4.0, 51.0], [4.05, 51.0]]))
        ->toThrow(RuntimeException::class);
});
