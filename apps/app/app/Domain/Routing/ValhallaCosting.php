<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Turns the rider's filters into Valhalla costing options.
 * Surface, hills, and sport each change which roads are chosen.
 */
final class ValhallaCosting
{
    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return array<string, mixed>
     */
    public static function payload(RouteQuery $query, array $waypoints, ?int $departHeading = null, bool $mustVisit = false, ?int $viaRadius = null): array
    {
        $useHills = match ($query->elevation) {
            ElevationBias::Minimize => 0.0,
            ElevationBias::Neutral => 0.5,
            ElevationBias::Maximize => 1.0,
        };
        $avoidBadSurfaces = match ($query->surface) {
            Surface::Paved => 1.0,
            Surface::Mixed => 0.35,
            Surface::Dirt => 0.0,
        };

        $onFoot = match ($query->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => true,
            Sport::Road, Sport::Gravel, Sport::Mtb => false,
        };

        if ($onFoot) {
            return [
                'locations' => self::locations($waypoints, $query->shape, $departHeading, $mustVisit, $viaRadius),
                'costing' => 'pedestrian',
                'costing_options' => [
                    'pedestrian' => [
                        'walking_speed' => $query->sport === Sport::Walk ? 5.0 : 6.0,
                        'use_hills' => $useHills,
                        'sidewalk_factor' => $query->surface === Surface::Dirt ? 0.1 : 1.0,
                        'use_living_streets' => $query->surface === Surface::Paved ? 0.6 : 0.2,
                    ],
                ],
            ];
        }

        $bicycleType = match ($query->sport) {
            Sport::Mtb => 'Mountain',
            Sport::Gravel => 'Cross',
            default => 'Road',
        };
        $useRoads = match ($query->surface) {
            Surface::Paved => 0.85,
            Surface::Mixed => 0.45,
            Surface::Dirt => 0.1,
        };

        if ($query->preferQuiet) {
            $useRoads = min($useRoads, 0.2);
        }

        return [
            'locations' => self::locations($waypoints, $query->shape, $departHeading, $mustVisit, $viaRadius),
            'costing' => 'bicycle',
            'costing_options' => [
                'bicycle' => [
                    'bicycle_type' => $bicycleType,
                    'use_hills' => $useHills,
                    'avoid_bad_surfaces' => $avoidBadSurfaces,
                    'use_roads' => $useRoads,
                ],
            ],
        ];
    }

    /**
     * A shaped loop uses via points, so the line can pass them without a stop.
     * A built point has to be ridden through on its own street. A wide search
     * would let the line take the next road over, so the search stays tight
     * and a mid-route point is a through point: no turnaround there. If the
     * direct turn would miss the street, the ride goes around and enters it.
     * On a loop the start and finish share one heading, so the line crosses
     * the pin instead of riding the same road back.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<array<string, float|int|string>>
     */
    private static function locations(array $waypoints, Shape $shape, ?int $departHeading = null, bool $mustVisit = false, ?int $viaRadius = null): array
    {
        $last = count($waypoints) - 1;
        $outbound = null;

        if ($shape === Shape::Loop && $last >= 1) {
            $first = $waypoints[0] ?? null;
            $next = $waypoints[1] ?? null;

            if ($first !== null && $next !== null) {
                $outbound = (int) round(self::bearing($first, $next));
            }
        }

        $locations = [];

        foreach ($waypoints as $index => $point) {
            $location = ['lon' => $point[0], 'lat' => $point[1]];

            if ($mustVisit && $index > 0) {
                $location['radius'] = 15;

                if ($index < $last) {
                    $location['type'] = 'through';
                }
            } elseif ($mustVisit) {
                $location['radius'] = 40;
            } elseif ($index > 0 && $index < $last) {
                $location['type'] = $viaRadius === null ? 'via' : 'through';

                if ($viaRadius !== null) {
                    $location['radius'] = $viaRadius;
                }
            }

            if ($departHeading !== null && $index === 0) {
                $location['heading'] = $departHeading;
                $location['heading_tolerance'] = 80;
            } elseif ($outbound !== null && ($index === 0 || $index === $last)) {
                $location['heading'] = $outbound;
                $location['heading_tolerance'] = 120;
            }

            $locations[] = $location;
        }

        return $locations;
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function bearing(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLon = deg2rad($to[0] - $from[0]);
        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
