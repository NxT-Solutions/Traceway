<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * A route after one change: the whole line, the points it passes now, and
 * which of its legs were planned again.
 */
final readonly class Reshaped
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  list<int>  $changed
     */
    public function __construct(
        public array $coordinates,
        public array $waypoints,
        public array $changed,
        public int $distanceMetres,
    ) {}
}
