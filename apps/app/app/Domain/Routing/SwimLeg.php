<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * The open-water part of a plan. The routing engine does not route on water,
 * so the swim is a pin or a line the athlete draws, kept as drawn.
 */
final readonly class SwimLeg
{
    public const int MOST_POINTS = 20;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(public array $coordinates)
    {
        if ($this->coordinates === [] || count($this->coordinates) > self::MOST_POINTS) {
            throw new InvalidArgumentException('A swim is 1 to 20 points.');
        }

        foreach ($this->coordinates as [$longitude, $latitude]) {
            if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                throw new InvalidArgumentException('A swim point is out of range.');
            }
        }
    }

    public function distanceMetres(): int
    {
        return (int) round(Distance::along($this->coordinates));
    }
}
