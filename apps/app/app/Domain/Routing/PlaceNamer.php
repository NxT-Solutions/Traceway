<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Names the place around a point: the village, town, or quarter where a pin
 * was dropped.
 */
interface PlaceNamer
{
    /**
     * The name of the place at the point, or null when the map has none.
     */
    public function nameAt(float $latitude, float $longitude): ?string;
}
