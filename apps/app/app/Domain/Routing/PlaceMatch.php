<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * One answer to a place search: a short name a rider recognises, the area
 * that tells two places of the same name apart, what kind of place it is,
 * and where it is.
 */
final readonly class PlaceMatch
{
    public function __construct(
        public string $name,
        public string $area,
        public float $latitude,
        public float $longitude,
        public PlaceKind $kind = PlaceKind::Place,
    ) {
        throw_if($this->latitude < -90 || $this->latitude > 90, InvalidArgumentException::class, 'Latitude must be between -90 and 90.');
        throw_if($this->longitude < -180 || $this->longitude > 180, InvalidArgumentException::class, 'Longitude must be between -180 and 180.');
    }
}
