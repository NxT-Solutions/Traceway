<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * The height of the ground at a distance along the route.
 */
final readonly class ProfilePoint
{
    public function __construct(
        public int $distanceMetres,
        public float $elevationMetres,
    ) {}
}
