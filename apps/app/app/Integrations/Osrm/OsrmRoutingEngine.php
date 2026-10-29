<?php

declare(strict_types=1);

namespace App\Integrations\Osrm;

use App\Domain\Routing\AvoidCheck;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineDistance;
use App\Domain\Routing\LoopClosure;
use App\Domain\Routing\LoopSearch;
use App\Domain\Routing\LoopWaypoints;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Integrations\JsonBody;
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

        // The search every engine uses. Two rounds, not three: the public
        // backup is asked about one request a second.
        return LoopSearch::loops($routeQuery, fn (array $waypointLists): array => $this->drawEach($routeQuery, $profile, $waypointLists), rounds: 2);
    }

    /**
     * Each list of waypoints drawn in turn, with null where no road fits. It
     * throws MapServerBusy only when the server answered none of them.
     *
     * @param  list<list<array{0: float, 1: float}>>  $waypointLists
     * @return list<GeneratedRoute|null>
     */
    private function drawEach(RouteQuery $routeQuery, string $profile, array $waypointLists): array
    {
        $routes = [];
        $busy = null;
        $answered = false;

        foreach ($waypointLists as $waypoints) {
            try {
                $routes[] = $this->fetch($routeQuery, $profile, $waypoints);
                $answered = true;
            } catch (MapServerBusy $mapServerBusy) {
                $routes[] = null;
                $busy = $mapServerBusy;
            } catch (RuntimeException) {
                // The server answered: no road fits this way.
                $routes[] = null;
                $answered = true;
            }
        }

        if ($answered || ! $busy instanceof MapServerBusy) {
            return $routes;
        }

        throw $busy;
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
     * OSRM cannot leave a way out, so a plan that skips stretches asks for
     * the alternatives too and takes the first that keeps off them. When
     * none does, it goes around the first stretch it rode, once, through a
     * point beside it. When that rides one as well, the first line comes
     * back and the planner says which stretch it still uses.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $routeQuery, string $profile, array $waypoints): GeneratedRoute
    {
        // OSRM offers alternatives only between two points.
        $lines = $this->lines($routeQuery, $profile, $waypoints, alternatives: $routeQuery->avoid !== [] && count($waypoints) === 2);

        if ($routeQuery->avoid === []) {
            return $lines[0];
        }

        foreach ($lines as $line) {
            if (AvoidCheck::crossing($line->coordinates, $routeQuery->avoid) === []) {
                return $line;
            }
        }

        $first = $lines[0];
        $ridden = AvoidCheck::crossing($first->coordinates, $routeQuery->avoid)[0] ?? null;
        $stretch = array_find($routeQuery->avoid, fn (AvoidedStretch $avoidedStretch): bool => $avoidedStretch->id === $ridden);

        if (! $stretch instanceof AvoidedStretch) {
            return $first;
        }

        foreach ([1.0, -1.0] as $side) {
            try {
                $around = $this->lines($routeQuery, $profile, $this->around($first->coordinates, $waypoints, $stretch, $side), alternatives: false)[0];
            } catch (RuntimeException) {
                continue;
            }

            if (AvoidCheck::crossing($around->coordinates, $routeQuery->avoid) === []) {
                // The point beside the stretch only steered the line; the stops are the asked ones.
                return new GeneratedRoute($routeQuery, $around->coordinates, $around->distanceMetres, $around->ascentMetres, $waypoints);
            }
        }

        return $first;
    }

    /**
     * The waypoints with one more, 150 m off the middle of the stretch, put
     * between the two waypoints the line meets it between.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<array{0: float, 1: float}>
     */
    private function around(array $line, array $waypoints, AvoidedStretch $avoidedStretch, float $side): array
    {
        $lineDistance = LineDistance::of($avoidedStretch->shape);
        $middle = $lineDistance->pointAt($lineDistance->totalMetres() / 2);
        $before = $lineDistance->pointAt(max(0.0, $lineDistance->totalMetres() / 2 - 5));
        $after = $lineDistance->pointAt(min($lineDistance->totalMetres(), $lineDistance->totalMetres() / 2 + 5));
        $lonScale = 111_320.0 * max(0.01, cos(deg2rad($middle[1])));
        $dx = ($after[0] - $before[0]) * $lonScale;
        $dy = ($after[1] - $before[1]) * 111_320.0;
        $length = max(0.001, hypot($dx, $dy));
        $beside = [
            $middle[0] + (-$dy / $length) * 150.0 * $side / $lonScale,
            $middle[1] + ($dx / $length) * 150.0 * $side / 111_320.0,
        ];

        $ridden = LineDistance::of($line);
        $at = $ridden->project($middle)['metres'];
        $placed = [];
        $inserted = false;

        foreach ($waypoints as $index => $waypoint) {
            if (! $inserted && $index > 0 && $ridden->project($waypoint)['metres'] >= $at) {
                $placed[] = $beside;
                $inserted = true;
            }

            $placed[] = $waypoint;
        }

        if (! $inserted) {
            array_splice($placed, count($placed) - 1, 0, [$beside]);
        }

        return $placed;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return non-empty-list<GeneratedRoute>
     */
    private function lines(RouteQuery $routeQuery, string $profile, array $waypoints, bool $alternatives): array
    {
        $response = $this->osrmConnector->send(new CyclingRouteRequest($waypoints, $profile, $alternatives, $this->osrmConnector->serverPerProfile));

        throw_if(! $response->successful() || JsonBody::get($response, 'code') !== 'Ok', RuntimeException::class, 'The cycling router did not return a loop.');

        $routes = $response->json('routes');
        $lines = [];

        foreach (is_array($routes) ? $routes : [] as $route) {
            $geometry = is_array($route) ? ($route['geometry'] ?? null) : null;
            $coordinates = is_array($geometry) ? ($geometry['coordinates'] ?? []) : [];
            $distance = is_array($route) ? ($route['distance'] ?? null) : null;

            if (! is_array($coordinates) || $coordinates === [] || ! is_numeric($distance)) {
                continue;
            }

            /** @var list<array{0: float, 1: float}> $coordinates */
            $lines[] = new GeneratedRoute(
                query: $routeQuery,
                coordinates: $coordinates,
                distanceMetres: (int) round((float) $distance),
                ascentMetres: 0,
                waypoints: $waypoints,
            );
        }

        throw_if($lines === [], RuntimeException::class, 'The cycling router returned an empty line.');

        return $lines;
    }
}
