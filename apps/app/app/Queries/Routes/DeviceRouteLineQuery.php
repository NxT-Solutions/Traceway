<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\Sport;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

/**
 * The shape of a route for a preview on a watch or bike computer. It is thinned to a few hundred bytes, and each point is latitude first, as the device reads it.
 */
final class DeviceRouteLineQuery
{
    public const int MAX_POINTS = 150;

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function route(RouteId $routeId, int $riderId): array
    {
        return $this->preview(SavedRouteRow::findOwned($routeId->value, $riderId)->line('coordinates'));
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public function leg(RouteId $routeId, int $riderId, int $leg): array
    {
        $savedLeg = SavedRouteRow::findOwned($routeId->value, $riderId)->legs('legs')[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        return $this->preview($savedLeg->coordinates);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    private function preview(array $line): array
    {
        $tolerance = LineSimplifier::OVERVIEW_METRES;
        $thinned = LineSimplifier::simplify($line, $tolerance);

        while (count($thinned) > self::MAX_POINTS) {
            $tolerance *= 2;
            $thinned = LineSimplifier::simplify($line, $tolerance);
        }

        return array_map(
            fn (array $point): array => [round($point[1], 5), round($point[0], 5)],
            $thinned,
        );
    }
}
