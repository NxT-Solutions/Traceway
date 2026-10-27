<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * A named point on the map: where a loop starts, or a place it passes.
 */
final readonly class Place
{
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
    ) {
        if ($this->latitude < -90 || $this->latitude > 90) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90.');
        }

        if ($this->longitude < -180 || $this->longitude > 180) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180.');
        }
    }
}
