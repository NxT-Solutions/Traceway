<?php

declare(strict_types=1);

namespace App\Integrations\Osrm;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LoopClosure;
use App\Domain\Routing\LoopWaypoints;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Integrations\Osrm\Requests\CyclingRouteRequest;
use RuntimeException;

final class OsrmRoutingEngine implements RoutingEngine
{
    public function __construct(private readonly OsrmConnector $connector) {}

    public function route(RouteQuery $query): GeneratedRoute
    {
        $candidates = $this->candidates($query);

        return $candidates[0] ?? throw new RuntimeException('The cycling router did not return a loop.');
    }

    public function candidates(RouteQuery $query): array
    {
        $profile = match ($query->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => 'foot',
            Sport::Road, Sport::Gravel, Sport::Mtb => 'bike',
        };

        if ($query->shape !== Shape::Loop) {
            $waypoints = $query->shape === Shape::OutAndBack
                ? LoopWaypoints::outAndBack(
                    $query->startLatitude,
                    $query->startLongitude,
                    $query->distanceMetres,
                    $query->viaLatitude,
                    $query->viaLongitude,
                )
                : LoopWaypoints::around(
                    $query->startLatitude,
                    $query->startLongitude,
                    $query->distanceMetres,
                    0.0,
                    $query->viaLatitude,
                    $query->viaLongitude,
                );

            return [$this->fetch($query, $profile, $waypoints)];
        }

        $candidates = [];

        foreach ([20.0, 80.0, 140.0, 200.0] as $bearing) {
            try {
                $candidates[] = $this->fetch(
                    $query,
                    $profile,
                    LoopWaypoints::around(
                        $query->startLatitude,
                        $query->startLongitude,
                        $query->distanceMetres,
                        $bearing,
                        $query->viaLatitude,
                        $query->viaLongitude,
                    ),
                );
            } catch (RuntimeException) {
                continue;
            }
        }

        $diverse = RouteCleanliness::diverse($candidates, $query->distanceMetres);

        if ($diverse === []) {
            throw new RuntimeException('The cycling router did not return a loop.');
        }

        return $diverse;
    }

    public function trace(RouteQuery $query, array $waypoints): GeneratedRoute
    {
        if (count($waypoints) < 2) {
            throw new RuntimeException('A built route needs a start and another point.');
        }

        $profile = match ($query->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => 'foot',
            Sport::Road, Sport::Gravel, Sport::Mtb => 'bike',
        };

        return $this->fetch($query, $profile, $waypoints);
    }

    public function closeLoop(RouteQuery $query, array $ridden, array $from, array $to): GeneratedRoute
    {
        $found = [];

        foreach (LoopClosure::plans($ridden, $from, $to, $query->distanceMetres) as $plan) {
            try {
                $found[] = $this->trace($query, $plan['waypoints']);
            } catch (RuntimeException) {
                // A closing road that does not connect is skipped.
            }
        }

        return LoopClosure::cleanest($ridden, $found, $query->distanceMetres)
            ?? throw new RuntimeException('The cycling router did not return a loop.');
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $query, string $profile, array $waypoints): GeneratedRoute
    {
        $response = $this->connector->send(new CyclingRouteRequest($waypoints, $profile));

        if (! $response->successful() || $response->json('code') !== 'Ok') {
            throw new RuntimeException('The cycling router did not return a loop.');
        }

        /** @var list<array{0: float, 1: float}> $coordinates */
        $coordinates = $response->json('routes.0.geometry.coordinates') ?? [];
        $distance = $response->json('routes.0.distance');

        if ($coordinates === [] || ! is_numeric($distance)) {
            throw new RuntimeException('The cycling router returned an empty line.');
        }

        return new GeneratedRoute(
            query: $query,
            coordinates: $coordinates,
            distanceMetres: (int) round((float) $distance),
            ascentMetres: 0,
        );
    }
}
