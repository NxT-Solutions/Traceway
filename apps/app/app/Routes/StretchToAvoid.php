<?php

declare(strict_types=1);

namespace App\Routes;

/**
 * A way a rider asked the router to skip, as it is kept: a point on it, its
 * line between two junctions, its OSM way, and its name.
 */
final readonly class StretchToAvoid
{
    /**
     * @param  list<array{0: float, 1: float}>  $shape
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public array $shape,
        public ?int $wayId = null,
        public ?string $name = null,
    ) {}
}
