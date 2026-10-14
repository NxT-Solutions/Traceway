<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Finds places by name for the planner, so a rider can type a town, a
 * street, or a landmark instead of dropping a pin by hand.
 */
interface PlaceSearch
{
    /**
     * The places that match, the ones near the given point first, each named
     * the way people there name it.
     *
     * @return list<PlaceMatch>
     */
    public function search(string $query, float $nearLatitude, float $nearLongitude): array;
}
