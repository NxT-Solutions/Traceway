<?php

declare(strict_types=1);

use App\Integrations\Valhalla\Polyline;

covers(Polyline::class);

uses()->group('mutation');

it('roundtrips a line at valhalla precision', function (): void {
    $coordinates = [
        [3.72, 51.05],
        [3.73, 51.06],
        [3.72, 51.05],
    ];

    $decoded = Polyline::decode(Polyline::encode($coordinates));

    expect($decoded[0][0])->toEqualWithDelta(3.72, 0.00001)
        ->and($decoded[1][1])->toEqualWithDelta(51.06, 0.00001)
        ->and($decoded)->toHaveCount(3);
});

it('roundtrips a point west and south of the origin', function (): void {
    $decoded = Polyline::decode(Polyline::encode([[-3.72, -51.05]]));

    expect($decoded)->toHaveCount(1)
        ->and($decoded[0][0])->toEqualWithDelta(-3.72, 0.00001)
        ->and($decoded[0][1])->toEqualWithDelta(-51.05, 0.00001);
});

it('reads an empty encoding as no points', function (): void {
    expect(Polyline::encode([]))->toBe('')
        ->and(Polyline::decode(''))->toBe([]);
});
