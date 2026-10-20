<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Turns the rider's filters into Valhalla costing options.
 * Sport, surface, hills, and Quiet first each change which roads are chosen.
 */
final class ValhallaCosting
{
    /** How far a followed line may turn from its heading at a point. */
    public const int FOLLOW_HEADING_TOLERANCE = 45;

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  list<int|null>|null  $headings  The way a followed line goes at each point, in the same order.
     * @return array<string, mixed>
     */
    public static function payload(RouteQuery $routeQuery, array $waypoints, ?int $departHeading = null, bool $mustVisit = false, ?int $viaRadius = null, ?array $headings = null): array
    {
        // A followed line already chose its roads. The rider's surface, hill,
        // and road filters would only pull it off them and back again.
        if ($headings !== null) {
            return ['locations' => self::locations($waypoints, $routeQuery->shape, $departHeading, $mustVisit, $viaRadius, $headings)] + self::trace($routeQuery->sport);
        }

        $useHills = match ($routeQuery->elevation) {
            ElevationBias::Minimize => 0.0,
            ElevationBias::Neutral => 0.5,
            ElevationBias::Maximize => 1.0,
        };
        $onFoot = match ($routeQuery->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => true,
            Sport::Road, Sport::Gravel, Sport::Mtb => false,
        };

        if ($onFoot) {
            return [
                'locations' => self::locations($waypoints, $routeQuery->shape, $departHeading, $mustVisit, $viaRadius, $headings),
                'costing' => 'pedestrian',
                'costing_options' => [
                    'pedestrian' => self::pedestrian($routeQuery, $useHills),
                ],
            ];
        }

        $bicycleType = match ($routeQuery->sport) {
            Sport::Mtb => 'Mountain',
            Sport::Gravel => 'Cross',
            default => 'Road',
        };
        $useRoads = match ($routeQuery->surface) {
            Surface::Paved => 0.85,
            Surface::Mixed => 0.45,
            Surface::Dirt => 0.1,
        };

        if ($routeQuery->preferQuiet) {
            $useRoads = min($useRoads, 0.2);
        }

        return [
            'locations' => self::locations($waypoints, $routeQuery->shape, $departHeading, $mustVisit, $viaRadius, $headings),
            'costing' => 'bicycle',
            'costing_options' => [
                'bicycle' => [
                    'bicycle_type' => $bicycleType,
                    'use_hills' => $useHills,
                    'avoid_bad_surfaces' => self::avoidBadSurfaces($routeQuery->surface),
                    'use_roads' => $useRoads,
                ],
            ],
        ];
    }

    /**
     * The costing that matches a finished line to the roads under it. The
     * line is already chosen, so only the sport decides which roads fit.
     *
     * @return array{costing: string, costing_options: array<string, array<string, float|int|string>>}
     */
    public static function trace(Sport $sport): array
    {
        return match ($sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => [
                'costing' => 'pedestrian',
                'costing_options' => ['pedestrian' => [
                    'walking_speed' => self::walkingSpeed($sport),
                    'max_hiking_difficulty' => self::hikingDifficulty($sport),
                ]],
            ],
            Sport::Road, Sport::Gravel, Sport::Mtb => [
                'costing' => 'bicycle',
                'costing_options' => ['bicycle' => ['bicycle_type' => match ($sport) {
                    Sport::Mtb => 'Mountain',
                    Sport::Gravel => 'Cross',
                    default => 'Road',
                }]],
            ],
        };
    }

    /**
     * Valhalla's pedestrian costing weighs the kind of way, not a road's
     * class or its surface. A walkway, a track, and a living street each have
     * their own cost. A path, a cycleway, and a plain road all cost the same.
     * So on foot Paved cannot keep a walk on paved ground, and nothing keeps
     * it off a busy road. Quiet first and the surface each move their own:
     *
     * - Quiet first makes walkways and living streets cheaper. A walkway is a
     *   footway, or a sidewalk mapped as its own way: Valhalla cannot tell
     *   them apart. A living street is one where walkers have right of way.
     * - The surface sets tracks, which are mostly unpaved: Paved leaves them
     *   alone, Any makes them a little cheaper, and Dirt as cheap as Valhalla
     *   allows (a factor of 0.8). Dirt makes walkways cheaper too, since in a
     *   town the unpaved ground is mostly park and forest footpaths: priced
     *   like a track, they lost a third of it to the streets.
     * - A road tagged with a sidewalk costs the same as any other road: a
     *   factor below 1 would draw a dirt route onto town streets.
     *
     * @return array<string, float|int>
     */
    private static function pedestrian(RouteQuery $routeQuery, float $useHills): array
    {
        $quiet = $routeQuery->preferQuiet;

        return [
            'walking_speed' => self::walkingSpeed($routeQuery->sport),
            'use_hills' => $useHills,
            'walkway_factor' => $quiet || $routeQuery->surface === Surface::Dirt ? 0.5 : 1.0,
            'sidewalk_factor' => 1.0,
            'use_tracks' => match ($routeQuery->surface) {
                Surface::Paved => 0.5,
                Surface::Mixed => 0.75,
                Surface::Dirt => 1.0,
            },
            'use_living_streets' => $quiet ? 0.9 : 0.6,
            'max_hiking_difficulty' => self::hikingDifficulty($routeQuery->sport),
        ];
    }

    /** Kilometres an hour. A walk and a hike go at a walking pace. */
    private static function walkingSpeed(Sport $sport): float
    {
        return match ($sport) {
            Sport::Walk, Sport::Hike => 5.0,
            default => 6.0,
        };
    }

    /**
     * The hardest SAC grade a route may use. A trail run or a hike may take
     * a mountain path (T2). Valhalla's own limit is a hiking path (T1).
     */
    private static function hikingDifficulty(Sport $sport): int
    {
        return match ($sport) {
            Sport::TrailRun, Sport::Hike => 2,
            default => 1,
        };
    }

    /**
     * The costing a tapped point snaps with: the sport's own ways and no
     * others. On Paved a road bike may not ride a gravel track, so its point
     * may not land on one either.
     *
     * @return array{costing: string, costing_options: array<string, array<string, float|int|string>>}
     */
    public static function locate(Sport $sport, Surface $surface): array
    {
        $costing = self::trace($sport);
        $bicycle = $costing['costing_options']['bicycle'] ?? null;

        if ($bicycle === null) {
            return $costing;
        }

        return [
            'costing' => $costing['costing'],
            'costing_options' => ['bicycle' => $bicycle + ['avoid_bad_surfaces' => self::avoidBadSurfaces($surface)]],
        ];
    }

    /** 1.0 keeps a bike off any surface worse than its type rides well. */
    private static function avoidBadSurfaces(Surface $surface): float
    {
        return match ($surface) {
            Surface::Paved => 1.0,
            Surface::Mixed => 0.35,
            Surface::Dirt => 0.0,
        };
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
     * A followed line holds its heading at each point, so a point on a road
     * with a lane each way is met going the right way, and the line does not
     * ride past it, turn, and come back.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  list<int|null>|null  $headings
     * @return list<array<string, float|int|string>>
     */
    private static function locations(array $waypoints, Shape $shape, ?int $departHeading = null, bool $mustVisit = false, ?int $viaRadius = null, ?array $headings = null): array
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

            $heading = $headings[$index] ?? null;

            if ($heading !== null) {
                $location['heading'] = $heading;
                $location['heading_tolerance'] = self::FOLLOW_HEADING_TOLERANCE;
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
