<?php

declare(strict_types=1);

use App\Integrations\OpenFreeMap\VectorTile;
use Tests\Support\VectorTiles;

it('reads the points and tags of one layer', function (): void {
    $tile = VectorTiles::tile([
        'water' => [['at' => [0.5, 0.5], 'tags' => ['class' => 'lake']]],
        'poi' => [
            ['at' => [0.25, 0.75], 'tags' => ['class' => 'cafe', 'subclass' => 'cafe', 'name' => 'De Nete', 'rank' => 3]],
            ['at' => [0.5, 0.125], 'tags' => ['class' => 'toilets', 'subclass' => 'toilets']],
        ],
    ]);

    expect(VectorTile::points($tile, 'poi'))->toBe([
        ['tags' => ['class' => 'cafe', 'subclass' => 'cafe', 'name' => 'De Nete', 'rank' => 3], 'points' => [[0.25, 0.75]]],
        ['tags' => ['class' => 'toilets', 'subclass' => 'toilets'], 'points' => [[0.5, 0.125]]],
    ]);
});

it('reads a packed tile the same', function (): void {
    $tile = VectorTiles::tile(['poi' => [['at' => [0.5, 0.5], 'tags' => ['subclass' => 'drinking_water']]]]);

    expect(VectorTile::points((string) gzencode($tile), 'poi'))->toBe(VectorTile::points($tile, 'poi'));
});

it('finds nothing in a tile without the layer, or an empty one', function (): void {
    expect(VectorTile::points(VectorTiles::tile(['water' => [['at' => [0.5, 0.5], 'tags' => ['class' => 'lake']]]]), 'poi'))->toBe([])
        ->and(VectorTile::points('', 'poi'))->toBe([]);
});

it('says so when a tile is cut short', function (): void {
    $tile = VectorTiles::tile(['poi' => [['at' => [0.5, 0.5], 'tags' => ['subclass' => 'cafe']]]]);

    VectorTile::points(substr($tile, 0, -5), 'poi');
})->throws(RuntimeException::class, 'The map tile ends too soon.');

it('reads every point of a multi-point feature, and only the start of a line', function (): void {
    $tile = VectorTiles::tile(['poi' => [
        ['points' => [[0.25, 0.25], [0.75, 0.5]], 'tags' => ['subclass' => 'toilets']],
        ['line' => [[0.5, 0.5], [0.625, 0.75], [0.25, 0.125]], 'tags' => ['subclass' => 'cafe']],
        ['at' => [0.125, 0.875], 'tags' => ['subclass' => 'drinking_water']],
    ]]);

    expect(array_column(VectorTile::points($tile, 'poi'), 'points'))->toBe([
        [[0.25, 0.25], [0.75, 0.5]],
        [[0.5, 0.5]],
        [[0.125, 0.875]],
    ]);
});

it('places points by the extent the tile says, past its edge too', function (): void {
    $tile = VectorTiles::tile(['poi' => [['at' => [-0.0625, 1.03125], 'tags' => ['subclass' => 'cafe']]]], extent: 512);

    expect(VectorTile::points($tile, 'poi')[0]['points'])->toBe([[-0.0625, 1.03125]]);
});

it('does not unpack a tile that would be too big', function (): void {
    VectorTile::points((string) gzencode(str_repeat("\0", 9 << 20)), 'poi');
})->throws(RuntimeException::class, 'The map tile could not be unpacked.');
