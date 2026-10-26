<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * The way between two junctions under a tapped point, as the router sees it.
 */
final readonly class RoadEdge
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
