<?php

declare(strict_types=1);

use App\Domain\Routing\Shape;

it('reads how a finished line goes', function (): void {
    $loop = [[4.0, 51.0], [4.02, 51.0], [4.02, 51.02], [4.0, 51.02], [4.0, 51.0]];
    $outAndBack = [[4.0, 51.0], [4.01, 51.0], [4.02, 51.0], [4.01, 51.0], [4.0, 51.0]];
    $oneWay = [[4.0, 51.0], [4.01, 51.0], [4.02, 51.01]];

    expect(Shape::of($loop))->toBe(Shape::Loop)
        ->and(Shape::of($outAndBack))->toBe(Shape::OutAndBack)
        ->and(Shape::of($oneWay))->toBe(Shape::PointToPoint)
        ->and(Shape::of([]))->toBe(Shape::Loop);
});
