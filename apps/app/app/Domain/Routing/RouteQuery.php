<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

final readonly class RouteQuery
{
    public function __construct(
        public Sport $sport,
        public Shape $shape,
        public int $distanceMetres,
        public Surface $surface,
        public ElevationBias $elevation,
        public float $startLatitude,
        public float $startLongitude,
        public ?float $viaLatitude = null,
        public ?float $viaLongitude = null,
        public bool $preferQuiet = false,
    ) {
        if ($this->distanceMetres < 1_000 || $this->distanceMetres > 300_000) {
            throw new InvalidArgumentException('Distance must be between 1 km and 300 km.');
        }

        if ($this->startLatitude < -90 || $this->startLatitude > 90) {
            throw new InvalidArgumentException('Start latitude is out of range.');
        }

        if ($this->startLongitude < -180 || $this->startLongitude > 180) {
            throw new InvalidArgumentException('Start longitude is out of range.');
        }

        if (($this->viaLatitude === null) !== ($this->viaLongitude === null)) {
            throw new InvalidArgumentException('A via point needs both a latitude and a longitude.');
        }

        if ($this->viaLatitude !== null && ($this->viaLatitude < -90 || $this->viaLatitude > 90)) {
            throw new InvalidArgumentException('Via latitude is out of range.');
        }

        if ($this->viaLongitude !== null && ($this->viaLongitude < -180 || $this->viaLongitude > 180)) {
            throw new InvalidArgumentException('Via longitude is out of range.');
        }
    }
}
