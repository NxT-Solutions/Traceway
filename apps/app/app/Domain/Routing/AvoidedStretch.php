<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * A stretch of road a rider asked the router to skip: one way between two
 * junctions. The point lies on it, so the router can tell which way it is.
 * The shape is what the map draws.
 */
final readonly class AvoidedStretch
{
    /**
     * @param  list<array{0: float, 1: float}>  $shape
     */
    public function __construct(
        public string $id,
        public float $latitude,
        public float $longitude,
        public array $shape,
        public ?string $name = null,
    ) {
        throw_if($this->latitude < -90 || $this->latitude > 90, InvalidArgumentException::class, 'An avoided stretch has a latitude out of range.');
        throw_if($this->longitude < -180 || $this->longitude > 180, InvalidArgumentException::class, 'An avoided stretch has a longitude out of range.');
        throw_if(count($this->shape) < 2, InvalidArgumentException::class, 'An avoided stretch needs a line of two points or more.');
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function point(): array
    {
        return [$this->longitude, $this->latitude];
    }
}
