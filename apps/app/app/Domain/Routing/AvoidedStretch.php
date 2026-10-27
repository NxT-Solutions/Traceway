<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * A stretch of road a rider asked the router to skip: one way between two
 * junctions. The point lies in its middle, so the router can tell which way
 * it is. The shape is what the map draws. The sport and surface are the ones
 * it was found for: a footpath found on foot is not sent with a bike plan,
 * where the router would take the road beside it for it.
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
        public Sport $sport = Sport::Road,
        public Surface $surface = Surface::Paved,
    ) {
        if ($this->latitude < -90 || $this->latitude > 90) {
            throw new InvalidArgumentException('An avoided stretch has a latitude out of range.');
        }

        if ($this->longitude < -180 || $this->longitude > 180) {
            throw new InvalidArgumentException('An avoided stretch has a longitude out of range.');
        }

        if (count($this->shape) < 2) {
            throw new InvalidArgumentException('An avoided stretch needs a line of two points or more.');
        }
    }

    /** Whether a plan for the sport can be sent this stretch. */
    public function concerns(Sport $sport): bool
    {
        return $sport->onFoot() || ! $this->sport->onFoot();
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function point(): array
    {
        return [$this->longitude, $this->latitude];
    }
}
