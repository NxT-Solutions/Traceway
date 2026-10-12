<?php

declare(strict_types=1);

use App\Domain\Routing\Detour;
use App\Domain\Routing\Distance;

covers(Detour::class, Distance::class);

uses()->group('mutation');

it('puts one point on each side of the line so the way through it covers the length', function (): void {
    $from = [4.84, 51.12];
    $to = [4.88, 51.10];
    $vias = Detour::vias($from, $to, 12_000, 1.2);

    expect($vias)->toHaveCount(2);

    foreach ($vias as $via) {
        $through = Distance::metres($from, $via) + Distance::metres($via, $to);

        expect($through)->toEqualWithDelta(10_000, 100);
    }

    $middle = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];

    expect(Distance::metres($middle, $vias[0]))->toEqualWithDelta(Distance::metres($middle, $vias[1]), 5);
});

it('reaches further out when scaled', function (): void {
    $from = [4.84, 51.12];
    $to = [4.88, 51.10];
    $middle = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];
    $near = Detour::vias($from, $to, 12_000, 1.2)[0];
    $far = Detour::vias($from, $to, 12_000, 1.2, 1.5)[0];

    expect(Distance::metres($middle, $far))->toEqualWithDelta(Distance::metres($middle, $near) * 1.5, 1);
});

it('sends a leg that finishes where it started out and back by half the length', function (): void {
    $here = [4.84, 51.12];

    foreach (Detour::vias($here, $here, 10_000, 1.25) as $via) {
        expect(Distance::metres($here, $via))->toEqualWithDelta(4_000, 20);
    }
});

it('stays on the line when the length is shorter than the gap', function (): void {
    $from = [4.84, 51.12];
    $to = [4.88, 51.10];
    $middle = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];

    foreach (Detour::vias($from, $to, 1_000, 1.0) as $via) {
        expect(Distance::metres($middle, $via))->toBeLessThan(1.0);
    }
});
