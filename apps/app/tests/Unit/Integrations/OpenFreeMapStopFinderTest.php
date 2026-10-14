<?php

declare(strict_types=1);

use App\Domain\Routing\Details\FoundStop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Sport;
use App\Integrations\OpenFreeMap\OpenFreeMapConnector;
use App\Integrations\OpenFreeMap\OpenFreeMapStopFinder;
use App\Integrations\OpenFreeMap\Requests\TileIndexRequest;
use App\Integrations\OpenFreeMap\Requests\TileRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Support\VectorTiles;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  Closure(int, int): string  $tile
 * @param  list<string>  $asked
 * @param  int|Closure(string): int  $status
 */
function tileFinder(Closure $tile, array &$asked = [], int|Closure $status = 200): OpenFreeMapStopFinder
{
    $connector = new OpenFreeMapConnector('https://tiles.test');
    $connector->withMockClient(new MockClient([
        TileIndexRequest::class => MockResponse::make(['tiles' => ['https://tiles.test/planet/20261011_080001_pt/{z}/{x}/{y}.pbf']]),
        TileRequest::class => function (PendingRequest $pendingRequest) use ($tile, &$asked, $status): MockResponse {
            $request = $pendingRequest->getRequest();
            assert($request instanceof TileRequest);
            $asked[] = $request->resolveEndpoint();

            return MockResponse::make($tile($request->x, $request->y), is_int($status) ? $status : $status($request->resolveEndpoint()));
        },
    ]));

    return new OpenFreeMapStopFinder($connector);
}

// Westerlo sits in z14 tile 8415/5478, from 4.8999 to 4.9219 east and
// 51.0828 to 51.0966 north.
$westerlo = [[4.905, 51.0905], [4.915, 51.0905]];

it('finds water, toilets, and cafés in the tiles along a line', function () use ($westerlo): void {
    $asked = [];
    $stops = tileFinder(fn (int $x, int $y): string => $x === 8415 && $y === 5478 ? VectorTiles::tile([
        'poi' => [
            ['at' => [0.5, 0.5], 'tags' => ['class' => 'drinking_water', 'subclass' => 'drinking_water']],
            ['at' => [0.25, 0.5], 'tags' => ['class' => 'cafe', 'subclass' => 'cafe', 'name' => 'De Nete']],
            ['at' => [0.75, 0.5], 'tags' => ['class' => 'toilets', 'subclass' => 'toilets', 'name' => '']],
            ['at' => [0.6, 0.5], 'tags' => ['class' => 'shop', 'subclass' => 'bakery']],
        ],
    ]) : '', $asked)->along(Sport::Road, $westerlo);

    expect(array_map(fn (FoundStop $foundStop): StopKind => $foundStop->kind, $stops))->toBe([StopKind::Water, StopKind::Cafe, StopKind::Toilet])
        ->and($stops[1]->name)->toBe('De Nete')
        ->and($stops[2]->name)->toBeNull()
        ->and($stops[0]->longitude)->toEqualWithDelta(4.9109, 0.0001)
        ->and($stops[0]->latitude)->toEqualWithDelta(51.0897, 0.0001)
        ->and($asked)->toBe(['/planet/20261011_080001_pt/14/8415/5478.pbf']);
});

it('keeps a stop once when two tiles both hold it', function (): void {
    $stops = tileFinder(fn (int $x, int $y): string => VectorTiles::tile([
        'poi' => [['at' => $x === 8415 ? [1.0, 0.5] : [0.0, 0.5], 'tags' => ['subclass' => 'cafe']]],
    ]))->along(Sport::Road, [[4.92, 51.0905], [4.9235, 51.0905]]);
    expect($stops)->toHaveCount(1);
});

it('looks only at the tiles within reach of the line', function (): void {
    $asked = [];

    tileFinder(fn (): string => '', $asked)->along(Sport::Run, [[4.905, 51.0905], [4.905, 51.06]]);

    sort($asked);

    expect($asked)->toBe([
        '/planet/20261011_080001_pt/14/8415/5478.pbf',
        '/planet/20261011_080001_pt/14/8415/5479.pbf',
        '/planet/20261011_080001_pt/14/8415/5480.pbf',
    ]);
});

it('fails loudly when a tile does not load, so no stops are kept as none', function () use ($westerlo): void {
    tileFinder(fn (): string => '', status: 503)->along(Sport::Road, $westerlo);
})->throws(RuntimeException::class, 'A map tile did not load.');

it('fails loudly when the tiles do not say where they are', function () use ($westerlo): void {
    $connector = new OpenFreeMapConnector('https://tiles.test');
    $connector->withMockClient(new MockClient([TileIndexRequest::class => MockResponse::make(['tiles' => []])]));

    new OpenFreeMapStopFinder($connector)->along(Sport::Road, $westerlo);
})->throws(RuntimeException::class, 'The map tiles did not say where they are.');

it('asks again for a tile that did not load the first time', function () use ($westerlo): void {
    $asked = [];
    $stops = tileFinder(
        fn (): string => VectorTiles::tile(['poi' => [['at' => [0.5, 0.5], 'tags' => ['subclass' => 'cafe']]]]),
        $asked,
        function (string $endpoint) use (&$asked): int {
            return count(array_keys($asked, $endpoint, strict: true)) === 1 ? 503 : 200;
        },
    )->along(Sport::Road, $westerlo);

    expect($stops)->toHaveCount(1)
        ->and($asked)->toBe(['/planet/20261011_080001_pt/14/8415/5478.pbf', '/planet/20261011_080001_pt/14/8415/5478.pbf']);
});

it('does not look along a line that crosses too many tiles', function (): void {
    $asked = [];
    $line = [];

    for ($degree = 0; $degree <= 40; $degree++) {
        $line[] = [(float) $degree, 70.0];
    }

    expect(fn (): array => tileFinder(fn (): string => '', $asked)->along(Sport::Road, $line))
        ->toThrow(RuntimeException::class, 'That line is too long to look for stops along.')
        ->and($asked)->toBe([]);
});

it('turns away a tile that is too big', function () use ($westerlo): void {
    tileFinder(fn (): string => str_repeat('x', (4 << 20) + 1))->along(Sport::Road, $westerlo);
})->throws(RuntimeException::class, 'A map tile was too big.');
