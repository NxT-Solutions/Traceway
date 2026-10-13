<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * A turn at a point on the route, and the street it turns onto.
 */
final readonly class Cue
{
    public function __construct(
        public int $distanceMetres,
        public float $longitude,
        public float $latitude,
        public Turn $turn,
        public ?string $street = null,
    ) {}
}
