<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * A stop the map knows near the route, before it is placed on the line.
 */
final readonly class FoundStop
{
    public function __construct(
        public StopKind $kind,
        public float $longitude,
        public float $latitude,
        public ?string $name = null,
    ) {}
}
