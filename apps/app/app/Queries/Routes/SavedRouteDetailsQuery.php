<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Sport;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

/**
 * The climb, surfaces, turns, and stops kept with a saved route, or with one
 * land leg of a multisport plan. Null for a route saved before they were read.
 */
final class SavedRouteDetailsQuery
{
    public function findOwned(RouteId $routeId, int $riderId, ?int $leg = null): ?RouteDetails
    {
        $rowReader = SavedRouteRow::findOwned($routeId->value, $riderId);

        if ($leg === null) {
            return $rowReader->details('details');
        }

        $savedLeg = $rowReader->legs('legs')[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        return $savedLeg->details;
    }
}
