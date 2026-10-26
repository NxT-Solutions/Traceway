<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use App\Domain\Routing\Distance;
use App\Domain\Routing\FollowPoint;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\LoopClosure;
use App\Domain\Routing\LoopWaypoints;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use App\Domain\Routing\Shape;
use App\Domain\Routing\ValhallaCosting;
use App\Domain\Routing\WaypointPieces;
use App\Integrations\Valhalla\Requests\RouteRequest;
use App\Integrations\Valhalla\Requests\TraceRouteRequest;
use RuntimeException;
use Saloon\Http\Response;
use Throwable;

final readonly class ValhallaRoutingEngine implements LineFollower, RoutingEngine
{
    /** The public server matches at most about this much of a line at once. */
    private const int MAX_MATCH_METRES = 150_000;

    /**
     * @param  int  $maxRequestMetres  The straight-line span one request may cover. The public
     *                                 server refuses more than 150 km, so a longer route is
     *                                 asked for in pieces.
     * @param  int  $maxRequestPoints  The points one request may hold. The public server takes ten.
     */
    public function __construct(
        private ValhallaConnector $valhallaConnector,
        private int $maxRequestMetres = 140_000,
        private int $maxRequestPoints = 10,
    ) {}

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
        $plans = [];

        foreach ([12.0, 72.0, 132.0, 192.0, 252.0, 312.0] as $bearing) {
            $plans[] = ['waypoints' => LoopWaypoints::around(
                $routeQuery->startLatitude,
                $routeQuery->startLongitude,
                $routeQuery->distanceMetres,
                $bearing,
                $routeQuery->viaLatitude,
                $routeQuery->viaLongitude,
                $radiusScale,
                $stops,
                $offsetCenter,
            )];
        }

        // A direction that does not fit, or that the server did not answer, is skipped.
        return array_values(array_filter($this->routeAll($routeQuery, $plans)));
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
            avoid: $routeQuery->avoid,
        ), $waypoints, mustVisit: true);
    }

    public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute
    {
        if (count($line) < 2 || Distance::along($line) > self::MAX_MATCH_METRES) {
            return null;
        }

        // A timeout or an error page is no match either: following the
        // points still works, so the caller tries that next.
        try {
            $response = $this->valhallaConnector->send(new TraceRouteRequest([
                'encoded_polyline' => Polyline::encode(LineSimplifier::densify($line, 100.0)),
                'shape_format' => 'polyline6',
                'shape_match' => 'map_snap',
                'trace_options' => ['search_radius' => 25, 'breakage_distance' => 2000],
                'directions_options' => ['directions_type' => 'none'],
            ] + ValhallaCosting::trace($routeQuery->sport)));

            if (! $response->successful()) {
                return null;
            }

            $legs = $response->json('trip.legs');
            $length = $response->json('trip.summary.length');
        } catch (Throwable) {
            return null;
        }

        // A line matched in parts has gaps between them. Points do better.
        if (! is_array($legs) || count($legs) !== 1 || ! is_numeric($length)) {
            return null;
        }

        $leg = $legs[0] ?? null;
        $shape = is_array($leg) ? ($leg['shape'] ?? null) : null;

        if (! is_string($shape) || $shape === '') {
            return null;
        }

        return new GeneratedRoute($routeQuery, Polyline::decode($shape), (int) round(((float) $length) * 1000), 0);
    }

    public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute
    {
        throw_if(count($points) < 2, RuntimeException::class, 'A followed line needs a start and another point.');

        $line = $this->routeAll($this->pointToPoint($routeQuery), [[
            'waypoints' => array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points),
            'headings' => array_map(fn (FollowPoint $followPoint): ?int => $followPoint->heading, $points),
            'mustVisit' => true,
        ]])[0] ?? null;

        return $line ?? throw new RuntimeException('The roads along that line did not join up.');
    }

    public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
    {
        $plans = [];

        foreach (LoopClosure::plans($ridden, $from, $to, $routeQuery->distanceMetres) as $plan) {
            $plans[] = [
                'waypoints' => $plan['waypoints'],
                'heading' => $plan['heading'],
                'viaRadius' => $plan['via_radius'] ?? null,
            ];
        }

        // A closing road that does not connect, or that the server did not answer, is skipped.
        $found = array_values(array_filter($this->routeAll($routeQuery, $plans)));

        return LoopClosure::cleanest($ridden, $found, $routeQuery->distanceMetres)
            ?? $this->trace($routeQuery, [$from, $to]);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fetch(RouteQuery $routeQuery, array $waypoints, bool $mustVisit = false): GeneratedRoute
    {
        return $this->routeAll($routeQuery, [['waypoints' => $waypoints, 'mustVisit' => $mustVisit]])[0]
            ?? throw new RuntimeException('No loop matched those filters.');
    }

    /**
     * Asks for every plan at once. A plan longer than one request may cover
     * goes out in pieces, and its pieces are joined back into one route. A
     * plan with a piece that does not route comes back as null.
     *
     * @param  list<array{waypoints: list<array{0: float, 1: float}>, heading?: int|null, mustVisit?: bool, viaRadius?: int|null, headings?: list<int|null>}>  $plans
     * @return list<GeneratedRoute|null>
     */
    private function routeAll(RouteQuery $routeQuery, array $plans): array
    {
        $requests = [];
        $pieceCounts = [];

        foreach ($plans as $planIndex => $plan) {
            $pieces = WaypointPieces::split($plan['waypoints'], $this->maxRequestMetres, $this->maxRequestPoints);
            // Loop headings hold the start and the finish of a whole loop, not of a piece.
            $pieceQuery = count($pieces) > 1 ? $this->pointToPoint($routeQuery) : $routeQuery;
            $pieceCounts[$planIndex] = count($pieces);

            $headings = isset($plan['headings']) ? $this->headingsAlong($plan['waypoints'], $plan['headings'], $pieces) : null;

            foreach ($pieces as $pieceIndex => $piece) {
                $requests[$planIndex.':'.$pieceIndex] = new RouteRequest(ValhallaCosting::payload(
                    $pieceQuery,
                    $piece,
                    $pieceIndex === 0 ? ($plan['heading'] ?? null) : null,
                    mustVisit: $plan['mustVisit'] ?? false,
                    viaRadius: $plan['viaRadius'] ?? null,
                    headings: $headings[$pieceIndex] ?? null,
                ));
            }
        }

        /** @var array<string, array{coordinates: list<array{0: float, 1: float}>, metres: int}> $lines */
        $lines = [];

        if ($requests !== []) {
            $this->valhallaConnector->pool(
                requests: $requests,
                concurrency: min(count($requests), 6),
                responseHandler: function (Response $response, int|string $key) use (&$lines): void {
                    try {
                        $lines[(string) $key] = $this->lineFrom($response);
                    } catch (RuntimeException) {
                        // A piece that does not route leaves its plan out.
                    }
                },
                exceptionHandler: static function (): void {
                    // A piece the server did not answer leaves its plan out.
                },
            )->send()->wait();
        }

        $routes = [];

        foreach ($pieceCounts as $planIndex => $count) {
            $coordinates = [];
            $metres = 0;

            for ($pieceIndex = 0; $pieceIndex < $count; $pieceIndex++) {
                $line = $lines[$planIndex.':'.$pieceIndex] ?? null;

                if ($line === null) {
                    $coordinates = null;

                    break;
                }

                $coordinates = $coordinates === [] ? $line['coordinates'] : array_merge($coordinates, array_slice($line['coordinates'], 1));
                $metres += $line['metres'];
            }

            $routes[] = $coordinates === null || $coordinates === [] ? null : $this->routeOf($routeQuery, $coordinates, $metres, $plans[$planIndex]['waypoints'] ?? []);
        }

        return $routes;
    }

    /**
     * The heading of each point in each piece. The pieces keep the points in
     * order, add points along long hops, and repeat each joint, so they are
     * walked in step with the points: the next point takes its heading, an
     * added point has none, and a joint keeps the one it had. A loop's start
     * and finish are the same place but keep their own headings.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  list<int|null>  $headings
     * @param  list<list<array{0: float, 1: float}>>  $pieces
     * @return list<list<int|null>>
     */
    private function headingsAlong(array $waypoints, array $headings, array $pieces): array
    {
        $next = 0;
        $joint = null;
        $along = [];

        foreach ($pieces as $pieceIndex => $piece) {
            $own = [];

            foreach ($piece as $pointIndex => $point) {
                if ($pieceIndex > 0 && $pointIndex === 0) {
                    $own[] = $joint;

                    continue;
                }

                $waypoint = $waypoints[$next] ?? null;

                if ($waypoint !== null && $waypoint[0] === $point[0] && $waypoint[1] === $point[1]) {
                    $own[] = $headings[$next] ?? null;
                    $next++;
                } else {
                    $own[] = null;
                }
            }

            $joint = $own[count($own) - 1] ?? null;
            $along[] = $own;
        }

        return $along;
    }

    private function pointToPoint(RouteQuery $routeQuery): RouteQuery
    {
        return new RouteQuery(
            sport: $routeQuery->sport,
            shape: Shape::PointToPoint,
            distanceMetres: $routeQuery->distanceMetres,
            surface: $routeQuery->surface,
            elevation: $routeQuery->elevation,
            startLatitude: $routeQuery->startLatitude,
            startLongitude: $routeQuery->startLongitude,
            preferQuiet: $routeQuery->preferQuiet,
            avoid: $routeQuery->avoid,
        );
    }

    /**
     * @return array{coordinates: list<array{0: float, 1: float}>, metres: int}
     */
    private function lineFrom(Response $response): array
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

        return ['coordinates' => $coordinates, 'metres' => (int) round(((float) $length) * 1000)];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function routeOf(RouteQuery $routeQuery, array $coordinates, int $distanceMetres, array $waypoints = []): GeneratedRoute
    {
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
            waypoints: $waypoints,
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
