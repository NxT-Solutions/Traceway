<?php

declare(strict_types=1);

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\Distance;
use App\Integrations\GraphHopper\GraphHopperAvoid;

it('prices each avoided stretch at zero in its own area', function (): void {
    $model = GraphHopperAvoid::customModel([
        new AvoidedStretch('a', 51.05, 3.722, [[3.72, 51.05], [3.724, 51.05]]),
        new AvoidedStretch('b', 51.06, 3.73, [[3.73, 51.059], [3.73, 51.061]]),
    ]);

    expect($model['ch.disable'])->toBeTrue()
        ->and($model['custom_model']['priority'])->toBe([
            ['if' => 'in_avoid_0', 'multiply_by' => '0'],
            ['if' => 'in_avoid_1', 'multiply_by' => '0'],
        ])
        ->and(array_column($model['custom_model']['areas']['features'], 'id'))->toBe(['avoid_0', 'avoid_1']);
});

it('draws a closed band about 12 m wide around the stretch', function (): void {
    $ring = GraphHopperAvoid::band([[3.72, 51.05], [3.724, 51.05]]);

    expect($ring[0])->toBe($ring[count($ring) - 1])
        ->and($ring)->toHaveCount(5)
        ->and(Distance::metres($ring[0], $ring[3]))->toEqualWithDelta(12.0, 0.5);
});

it('stops the band short of the junctions, so the roads that meet stay open', function (): void {
    $middle = GraphHopperAvoid::shortOfJunctions([[3.72, 51.05], [3.724, 51.05]]);

    expect(Distance::metres([3.72, 51.05], $middle[0]))->toEqualWithDelta(15.0, 0.5)
        ->and(Distance::metres([3.724, 51.05], $middle[count($middle) - 1]))->toEqualWithDelta(15.0, 0.5);
});

it('adds nothing when the rider avoids nothing', function (): void {
    expect(GraphHopperAvoid::customModel([]))->toBe([]);
});
