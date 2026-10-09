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

final readonly class OsrmRoutingEngine implements RoutingEngine
{
    public function __construct(private OsrmConnector $osrmConnector) {}

    public function route(RouteQuery $routeQuery): GeneratedRoute
    {
        $candidates = $this->candidates($routeQuery);

        return $candidates[0] ?? throw new RuntimeException('The cycling router did not return a loop.');
    }

    public function candidates(RouteQuery $routeQuery): array
    {
        $profile = match ($routeQuery->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => 'foot',
            Sport::Road, Sport::Gravel, Sport::Mtb => 'bike',
        };

        if ($routeQuery->shape !== Shape::Loop) {
            $waypoints = $routeQuery->shape === Shape::OutAndBack
                ? LoopWaypoints::outAndBack(
                    $routeQuery->startLatitude,
                    $routeQuery->startLongitude,
                    $routeQuery->distanceMetres,
                    $routeQuery->viaLatitude,
                    $routeQuery->viaLongitude,
                )
                : LoopWaypoints::around(
                    $routeQuery->startLatitude,
                    $routeQuery->startLongitude,
                    $routeQuery->distanceMetres,
                    0.0,
                    $routeQuery->viaLatitude,
                    $routeQuery->viaLongitude,
                );

            return [$this->fetch($routeQuery, $profile, $waypoints)];
        }

        $candidates = [];

        foreach ([20.0, 80.0, 140.0, 200.0] as $bearing) {
            try {
                $candidates[] = $this->fetch(
                    $routeQuery,
                    $profile,
                    LoopWaypoints::around(
                        $routeQuery->startLatitude,
                        $routeQuery->startLongitude,
                        $routeQuery->distanceMetres,
                        $bearing,
                        $routeQuery->viaLatitude,
                        $routeQuery->viaLongitude,
                    ),
                );
            } catch (RuntimeException) {
                continue;
            }
        }

        $diverse = RouteCleanliness::diverse($candidates, $routeQuery->distanceMetres);

        throw_if($diverse === [], RuntimeException::class, 'The cycling router did not return a loop.');

        return $diverse;
    }

    public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
    {
        throw_if(count($waypoints) < 2, RuntimeException::class, 'A built route needs a start and another point.');

        $profile = match ($routeQuery->sport) {
            Sport::Run, Sport::TrailRun, Sport::Walk, Sport::Hike => 'foot',
            Sport::Road, Sport::Gravel, Sport::Mtb => 'bike',
        };

        return $this->fetch($routeQuery, $profile, $waypoints);
    }

    public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
    {
        $found = [];

        foreach (LoopClosure::plans($ridden, $from, $to, $routeQuery->distanceMetres) as $plan) {
            try {
                $found[] = $this->trace($routeQuery, $plan['waypoints']);
            } catch (RuntimeException) {
                // A closing road that does not connect is skipped.
            }
        }

        return LoopClosure::cleanest($ridden, $found, $routeQuery->distanceMetres)
            ?? throw new RuntimeException('The cycling router did not return a loop.');
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $routeQuery, string $profile, array $waypoints): GeneratedRoute
    {
        $response = $this->osrmConnector->send(new CyclingRouteRequest($waypoints, $profile));

        throw_if(! $response->successful() || $response->json('code') !== 'Ok', RuntimeException::class, 'The cycling router did not return a loop.');

        /** @var list<array{0: float, 1: float}> $coordinates */
        $coordinates = $response->json('routes.0.geometry.coordinates') ?? [];
        $distance = $response->json('routes.0.distance');

        throw_if($coordinates === [] || ! is_numeric($distance), RuntimeException::class, 'The cycling router returned an empty line.');

        return new GeneratedRoute(
            query: $routeQuery,
            coordinates: $coordinates,
            distanceMetres: (int) round((float) $distance),
            ascentMetres: 0,
        );
    }
}
