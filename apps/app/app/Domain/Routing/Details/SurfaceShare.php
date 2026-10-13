<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * How much of the route runs on one surface.
 */
final readonly class SurfaceShare
{
    public function __construct(
        public RoadSurface $surface,
        public int $metres,
    ) {}
}
