<?php

declare(strict_types=1);

use App\Domain\Routing\LineSimplifier;

covers(LineSimplifier::class);

uses()->group('mutation');

it('drops points along a straight stretch and keeps both ends', function (): void {
    $line = [[4.90, 51.10], [4.91, 51.10], [4.92, 51.10], [4.93, 51.10], [4.94, 51.10]];

    expect(LineSimplifier::simplify($line, 1.0))->toBe([[4.90, 51.10], [4.94, 51.10]]);
});

it('keeps a corner and a bend wider than the tolerance', function (): void {
    $line = [[4.90, 51.10], [4.92, 51.10], [4.92, 51.12], [4.921, 51.13], [4.92, 51.14]];
    $kept = LineSimplifier::simplify($line, 5.0);

    expect($kept)->toContain([4.92, 51.10])
        ->and($kept)->toContain([4.921, 51.13])
        ->and($kept[0])->toBe([4.90, 51.10])
        ->and(array_last($kept))->toBe([4.92, 51.14]);
});

it('drops a wobble narrower than the tolerance', function (): void {
    // Five thousandths of a millidegree of latitude is about half a metre.
    $line = [[4.90, 51.10], [4.91, 51.100005], [4.92, 51.10]];

    expect(LineSimplifier::simplify($line, 1.0))->toBe([[4.90, 51.10], [4.92, 51.10]])
        ->and(LineSimplifier::simplify($line, 0.1))->toBe($line);
});

it('keeps a loop that comes back to its start', function (): void {
    $loop = [[4.90, 51.10], [4.95, 51.10], [4.95, 51.13], [4.90, 51.13], [4.90, 51.10]];

    expect(LineSimplifier::simplify($loop, 1.0))->toBe($loop);
});

it('leaves a short line and a zero tolerance alone', function (): void {
    expect(LineSimplifier::simplify([[4.9, 51.1], [4.91, 51.1]], 1.0))->toBe([[4.9, 51.1], [4.91, 51.1]])
        ->and(LineSimplifier::simplify([], 1.0))->toBe([])
        ->and(LineSimplifier::simplify([[4.90, 51.10], [4.91, 51.10], [4.92, 51.10]], 0.0))->toBe([[4.90, 51.10], [4.91, 51.10], [4.92, 51.10]]);
});
