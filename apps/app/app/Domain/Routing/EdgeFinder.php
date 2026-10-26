<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use RuntimeException;

interface EdgeFinder
{
    /**
     * The way under a point that the sport may use, or null when none is
     * close enough.
     *
     * @throws RuntimeException when the router did not answer.
     */
    public function near(float $latitude, float $longitude, Sport $sport, Surface $surface): ?RoadEdge;
}
