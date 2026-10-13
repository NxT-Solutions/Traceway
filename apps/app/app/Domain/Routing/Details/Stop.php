<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * A stop on the route. The point sits on the line, where the rider passes it.
 */
final readonly class Stop
{
    public function __construct(
        public int $distanceMetres,
        public float $longitude,
        public float $latitude,
        public StopKind $kind,
        public ?string $name = null,
    ) {}
}
