<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * One drawn leg of a multisport plan. A leg without a sport is the swim.
 */
final readonly class PlannedLeg
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public ?Sport $sport,
        public array $coordinates,
        public int $distanceMetres,
        public int $ascentMetres,
    ) {}

    public static function swim(SwimLeg $swimLeg): self
    {
        return new self(sport: null, coordinates: $swimLeg->coordinates, distanceMetres: $swimLeg->distanceMetres(), ascentMetres: 0);
    }

    public function discipline(): string
    {
        return $this->sport instanceof Sport ? $this->sport->value : 'swim';
    }
}
