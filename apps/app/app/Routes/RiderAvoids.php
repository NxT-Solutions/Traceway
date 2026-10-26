<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\Distance;
use App\Domain\Routing\MultisportQuery;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Queries\Routes\AvoidedStretchesQuery;

/**
 * Adds the rider's avoided stretches to a plan: the ones the route could
 * reach, nearest first. The server picks them, so every way of planning
 * (the map, the AI connector) skips the same stretches.
 */
final readonly class RiderAvoids
{
    /** Room past the farthest a route goes, for the roads it takes to get there. */
    private const float SLACK_METRES = 2_000.0;

    public function __construct(private AvoidedStretchesQuery $avoidedStretchesQuery) {}

    /**
     * @param  list<array{0: float, 1: float}>|null  $waypoints  The points a built route joins. Without them, the plan's own reach counts.
     */
    public function apply(RouteQuery $routeQuery, int $riderId, ?array $waypoints = null): RouteQuery
    {
        if ($waypoints !== null && $waypoints !== []) {
            $longitudes = array_column($waypoints, 0);
            $latitudes = array_column($waypoints, 1);
            $centre = [(min($longitudes) + max($longitudes)) / 2, (min($latitudes) + max($latitudes)) / 2];
            $reach = Distance::metres([min($longitudes), min($latitudes)], [max($longitudes), max($latitudes)]) / 2;
        } else {
            $centre = [$routeQuery->startLongitude, $routeQuery->startLatitude];
            $reach = match ($routeQuery->shape) {
                Shape::PointToPoint => (float) $routeQuery->distanceMetres,
                Shape::Loop, Shape::OutAndBack => $routeQuery->distanceMetres / 2,
            };
        }

        $avoid = $this->around($riderId, $centre, $reach, $routeQuery->sport);

        return $avoid === [] ? $routeQuery : $routeQuery->withAvoid($avoid);
    }

    public function applyToMultisport(MultisportQuery $multisportQuery, int $riderId): MultisportQuery
    {
        $avoid = $this->around($riderId, $multisportQuery->transition(), (float) $multisportQuery->reachMetres());

        return $avoid === [] ? $multisportQuery : $multisportQuery->withAvoid($avoid);
    }

    /**
     * Every leg's stretches together: the planner gives each leg the ones
     * for its own sport.
     *
     * @param  array{0: float, 1: float}  $centre
     * @return list<AvoidedStretch>
     */
    private function around(int $riderId, array $centre, float $reach, ?Sport $sport = null): array
    {
        return $this->avoidedStretchesQuery->near($riderId, $centre[1], $centre[0], $reach + self::SLACK_METRES, $sport);
    }
}
