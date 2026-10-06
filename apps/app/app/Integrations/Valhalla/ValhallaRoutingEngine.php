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

final class ValhallaRoutingEngine implements RoutingEngine
{
    public function __construct(private readonly ValhallaConnector $connector) {}

    public function route(RouteQuery $query): GeneratedRoute
    {
        $candidates = $this->candidates($query);

        return $candidates[0] ?? throw new RuntimeException('No loop matched those filters.');
    }

    public function candidates(RouteQuery $query): array
    {
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

            return [$this->fetch($query, $waypoints)];
        }

        $scale = 0.95;
        $found = $this->loops($query, $scale, 4, true);
        $best = RouteCleanliness::cleanest($found, $query->distanceMetres);

        if ($best instanceof GeneratedRoute && $best->distanceMetres > 0) {
            $error = abs($best->distanceMetres - $query->distanceMetres) / $query->distanceMetres;

            if ($error > 0.10) {
                $adjusted = min(1.45, max(0.5, $scale * ($query->distanceMetres / $best->distanceMetres)));
                $found = array_merge($found, $this->loops($query, $adjusted, 4, true));
            }
        }

        $diverse = RouteCleanliness::diverse($found, $query->distanceMetres);

        if ($diverse === []) {
            throw new RuntimeException('No loop matched those filters.');
        }

        return $diverse;
    }

    /**
     * @return list<GeneratedRoute>
     */
    private function loops(RouteQuery $query, float $radiusScale, int $stops, bool $offsetCenter): array
    {
        $requests = [];

        foreach ([12.0, 72.0, 132.0, 192.0, 252.0, 312.0] as $bearing) {
            $requests[] = new RouteRequest(ValhallaCosting::payload($query, LoopWaypoints::around(
                $query->startLatitude,
                $query->startLongitude,
                $query->distanceMetres,
                $bearing,
                $query->viaLatitude,
                $query->viaLongitude,
                $radiusScale,
                $stops,
                $offsetCenter,
            )));
        }

        $found = [];

        $this->connector->pool(
            requests: $requests,
            concurrency: count($requests),
            responseHandler: function (Response $response) use ($query, &$found): void {
                try {
                    $found[] = $this->routeFrom($query, $response);
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
    public function trace(RouteQuery $query, array $waypoints): GeneratedRoute
    {
        if (count($waypoints) < 2) {
            throw new RuntimeException('A built route needs a start and another point.');
        }

        return $this->fetch(new RouteQuery(
            sport: $query->sport,
            shape: Shape::PointToPoint,
            distanceMetres: $query->distanceMetres,
            surface: $query->surface,
            elevation: $query->elevation,
            startLatitude: $query->startLatitude,
            startLongitude: $query->startLongitude,
            preferQuiet: $query->preferQuiet,
        ), $waypoints, true);
    }

    public function closeLoop(RouteQuery $query, array $ridden, array $from, array $to): GeneratedRoute
    {
        $requests = [];

        foreach (LoopClosure::plans($ridden, $from, $to, $query->distanceMetres) as $plan) {
            $requests[] = new RouteRequest(ValhallaCosting::payload(
                $query,
                $plan['waypoints'],
                $plan['heading'],
                false,
                $plan['via_radius'] ?? null,
            ));
        }

        $found = [];

        $this->connector->pool(
            requests: $requests,
            concurrency: count($requests),
            responseHandler: function (Response $response) use ($query, &$found): void {
                try {
                    $found[] = $this->routeFrom($query, $response);
                } catch (RuntimeException) {
                    // A closing road that does not connect is skipped.
                }
            },
            exceptionHandler: static function (): void {
                // A closing road the server did not answer is skipped.
            },
        )->send()->wait();

        return LoopClosure::cleanest($ridden, $found, $query->distanceMetres)
            ?? $this->trace($query, [$from, $to]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $query, array $waypoints, bool $mustVisit = false): GeneratedRoute
    {
        return $this->routeFrom(
            $query,
            $this->connector->send(new RouteRequest(ValhallaCosting::payload($query, $waypoints, null, $mustVisit))),
        );
    }

    private function routeFrom(RouteQuery $query, Response $response): GeneratedRoute
    {
        $length = $response->json('trip.summary.length');
        /** @var list<array{shape?: string}> $legs */
        $legs = $response->json('trip.legs') ?? [];

        if (! $response->successful() || ! is_numeric($length) || $legs === []) {
            throw new RuntimeException('No loop matched those filters.');
        }

        $coordinates = [];

        foreach ($legs as $leg) {
            $shape = $leg['shape'] ?? '';

            if ($shape === '') {
                continue;
            }

            $coordinates = array_merge($coordinates, Polyline::decode($shape));
        }

        if ($coordinates === []) {
            throw new RuntimeException('The router returned an empty line.');
        }

        $distanceMetres = (int) round(((float) $length) * 1000);

        if ($query->shape === Shape::Loop) {
            $trimmed = RouteCleanliness::withoutSpurs($coordinates);

            if ($trimmed !== $coordinates) {
                $coordinates = $trimmed;
                $distanceMetres = self::lengthMetres($coordinates);
            }
        }

        return new GeneratedRoute(
            query: $query,
            coordinates: $coordinates,
            distanceMetres: $distanceMetres,
            ascentMetres: 0,
        );
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private static function lengthMetres(array $coordinates): int
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
