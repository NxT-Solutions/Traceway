<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LoopClosure;
use App\Domain\Routing\LoopWaypoints;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\ValhallaCosting;
use App\Integrations\Valhalla\Requests\RouteRequest;
use RuntimeException;
use Saloon\Http\Response;

final readonly class ValhallaRoutingEngine implements RoutingEngine
{
    public function __construct(private ValhallaConnector $valhallaConnector) {}

    public function route(RouteQuery $routeQuery): GeneratedRoute
    {
        $candidates = $this->candidates($routeQuery);

        return $candidates[0] ?? throw new RuntimeException('No loop matched those filters.');
    }

    public function candidates(RouteQuery $routeQuery): array
    {
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

            return [$this->fetch($routeQuery, $waypoints)];
        }

        $scale = 0.95;
        $found = $this->loops($routeQuery, $scale, 4, offsetCenter: true);
        $best = RouteCleanliness::cleanest($found, $routeQuery->distanceMetres);

        if ($best instanceof GeneratedRoute && $best->distanceMetres > 0) {
            $error = abs($best->distanceMetres - $routeQuery->distanceMetres) / $routeQuery->distanceMetres;

            if ($error > 0.10) {
                $adjusted = min(1.45, max(0.5, $scale * ($routeQuery->distanceMetres / $best->distanceMetres)));
                $found = array_merge($found, $this->loops($routeQuery, $adjusted, 4, offsetCenter: true));
            }
        }

        $diverse = RouteCleanliness::diverse($found, $routeQuery->distanceMetres);

        throw_if($diverse === [], RuntimeException::class, 'No loop matched those filters.');

        return $diverse;
    }

    /**
     * @return list<GeneratedRoute>
     */
    private function loops(RouteQuery $routeQuery, float $radiusScale, int $stops, bool $offsetCenter): array
    {
        $requests = [];

        foreach ([12.0, 72.0, 132.0, 192.0, 252.0, 312.0] as $bearing) {
            $requests[] = new RouteRequest(ValhallaCosting::payload($routeQuery, LoopWaypoints::around(
                $routeQuery->startLatitude,
                $routeQuery->startLongitude,
                $routeQuery->distanceMetres,
                $bearing,
                $routeQuery->viaLatitude,
                $routeQuery->viaLongitude,
                $radiusScale,
                $stops,
                $offsetCenter,
            )));
        }

        $found = [];

        $this->valhallaConnector->pool(
            requests: $requests,
            concurrency: count($requests),
            responseHandler: function (Response $response) use ($routeQuery, &$found): void {
                try {
                    $found[] = $this->routeFrom($routeQuery, $response);
                } catch (RuntimeException) {
                    // A direction that does not fit is skipped.
                }
            },
            exceptionHandler: static function (): void {
                // A direction the server did not answer is skipped.
            },
        )->send()->wait();

        return $found;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
    {
        throw_if(count($waypoints) < 2, RuntimeException::class, 'A built route needs a start and another point.');

        return $this->fetch(new RouteQuery(
            sport: $routeQuery->sport,
            shape: Shape::PointToPoint,
            distanceMetres: $routeQuery->distanceMetres,
            surface: $routeQuery->surface,
            elevation: $routeQuery->elevation,
            startLatitude: $routeQuery->startLatitude,
            startLongitude: $routeQuery->startLongitude,
            preferQuiet: $routeQuery->preferQuiet,
        ), $waypoints, mustVisit: true);
    }

    public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
    {
        $requests = [];

        foreach (LoopClosure::plans($ridden, $from, $to, $routeQuery->distanceMetres) as $plan) {
            $requests[] = new RouteRequest(ValhallaCosting::payload(
                $routeQuery,
                $plan['waypoints'],
                $plan['heading'],
                mustVisit: false,
                viaRadius: $plan['via_radius'] ?? null,
            ));
        }

        $found = [];

        $this->valhallaConnector->pool(
            requests: $requests,
            concurrency: count($requests),
            responseHandler: function (Response $response) use ($routeQuery, &$found): void {
                try {
                    $found[] = $this->routeFrom($routeQuery, $response);
                } catch (RuntimeException) {
                    // A closing road that does not connect is skipped.
                }
            },
            exceptionHandler: static function (): void {
                // A closing road the server did not answer is skipped.
            },
        )->send()->wait();

        return LoopClosure::cleanest($ridden, $found, $routeQuery->distanceMetres)
            ?? $this->trace($routeQuery, [$from, $to]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $routeQuery, array $waypoints, bool $mustVisit = false): GeneratedRoute
    {
        return $this->routeFrom(
            $routeQuery,
            $this->valhallaConnector->send(new RouteRequest(ValhallaCosting::payload($routeQuery, $waypoints, mustVisit: $mustVisit))),
        );
    }

    private function routeFrom(RouteQuery $routeQuery, Response $response): GeneratedRoute
    {
        $length = $response->json('trip.summary.length');
        /** @var list<array{shape?: string}> $legs */
        $legs = $response->json('trip.legs') ?? [];

        throw_if(! $response->successful() || ! is_numeric($length) || $legs === [], RuntimeException::class, 'No loop matched those filters.');

        $coordinates = [];

        foreach ($legs as $leg) {
            $shape = $leg['shape'] ?? '';

            if ($shape === '') {
                continue;
            }

            $coordinates = array_merge($coordinates, Polyline::decode($shape));
        }

        throw_if($coordinates === [], RuntimeException::class, 'The router returned an empty line.');

        $distanceMetres = (int) round(((float) $length) * 1000);

        if ($routeQuery->shape === Shape::Loop) {
            $trimmed = RouteCleanliness::withoutSpurs($coordinates);

            if ($trimmed !== $coordinates) {
                $coordinates = $trimmed;
                $distanceMetres = $this->lengthMetres($coordinates);
            }
        }

        return new GeneratedRoute(
            query: $routeQuery,
            coordinates: $coordinates,
            distanceMetres: $distanceMetres,
            ascentMetres: 0,
        );
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private function lengthMetres(array $coordinates): int
    {
        $metres = 0.0;

        for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
            $from = $coordinates[$index - 1] ?? null;
            $to = $coordinates[$index] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $lat1 = deg2rad($from[1]);
            $lat2 = deg2rad($to[1]);
            $dLat = $lat2 - $lat1;
            $dLon = deg2rad($to[0] - $from[0]);
            $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
            $metres += 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
        }

        return (int) round($metres);
    }
}
