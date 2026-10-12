<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Turns a place name or an address into a point on the map.
 */
interface PlaceFinder
{
    /**
     * The best match for the search, or null when the map knows no such place.
     */
    public function find(string $search): ?Place;
}
