<?php

declare(strict_types=1);

use App\Integrations\Valhalla\Polyline;

it('roundtrips a line at valhalla precision', function () {
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
