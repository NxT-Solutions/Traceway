<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * One leg of a multisport plan that the routing engine draws.
 */
final readonly class LandLeg
{
    /**
     * @param  array{0: float, 1: float}|null  $finish  Where a chained leg hands over, as [lon, lat].
     * @param  bool  $stretch  A chained leg covers its length on the way to the finish, rather than taking the shortest way.
     */
    public function __construct(
        public Sport $sport,
        public int $distanceMetres,
        public Surface $surface,
        public ElevationBias $elevation,
        public bool $preferQuiet = false,
        public ?array $finish = null,
        public bool $stretch = false,
    ) {
        if ($this->distanceMetres < 1_000 || $this->distanceMetres > 300_000) {
            throw new InvalidArgumentException('A leg must be between 1 km and 300 km.');
        }

        if ($this->finish !== null && ($this->finish[0] < -180 || $this->finish[0] > 180 || $this->finish[1] < -90 || $this->finish[1] > 90)) {
            throw new InvalidArgumentException('A leg finish is out of range.');
        }
    }
}
