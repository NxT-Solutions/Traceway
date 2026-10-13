<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Distance;
use App\Domain\Routing\FollowPoint;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineDistance;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use InvalidArgumentException;
use RuntimeException;

/**
 * Rides an imported line again: the other way round, or from the rider's own
 * door. The new line comes from the routing engine, through points picked
 * along the old one and the way it goes at each, so it follows real roads
 * for this sport and keeps going forward.
 */
final class ImportedLine
{
    /** A line that ends this close to its start is a loop. */
    public const float LOOP_METRES = 300.0;

    /** A routing server takes this many points in one request. */
    public const int POINTS_PER_TRACE = 40;

    /** The points sit at most this far apart along the line. */
    public const float SPACING_METRES = 750.0;

    /**
     * A new line may ride back over its own roads this much more than the
     * file does before the points are moved and the line drawn again.
     */
    public const float DOUBLING_SLACK = 0.01;

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    public static function isLoop(array $line): bool
    {
        $first = $line[0] ?? null;
        $last = $line[count($line) - 1] ?? null;

        return $first !== null && $last !== null && count($line) > 2 && Distance::metres($first, $last) <= self::LOOP_METRES;
    }

    /**
     * A loop begins where it passes closest to the start. Any other line is
     * left as it is, to be ridden from its own start.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}  $start
     * @return list<array{0: float, 1: float}>
     */
    public static function fromNearest(array $line, array $start): array
    {
        if (! self::isLoop($line)) {
            return $line;
        }

        $nearest = self::nearestIndex($line, $start);

        return array_merge(array_slice($line, $nearest), array_slice($line, 1, $nearest));
    }

    /**
     * Points along the line, close enough that the new line keeps to the old
     * roads, each with the way the line goes there. A shift moves the points
     * a share of the spacing along, to try again past a junction that went
     * wrong.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<FollowPoint>
     */
    public static function points(array $line, float $shift = 0.0): array
    {
        $lineDistance = LineDistance::of($line);
        $total = $lineDistance->totalMetres();
        $spacing = max(self::SPACING_METRES, $total / 200);
        $count = max(1, (int) ceil($total / $spacing));
        $step = $total / $count;
        $at = [0.0];

        for ($index = 1; $index < $count + ($shift > 0.0 ? 1 : 0); $index++) {
            $metres = $step * ($index - $shift);

            if ($metres > 0.0 && $metres < $total) {
                $at[] = $metres;
            }
        }

        $at[] = $total;

        return array_map(fn (float $metres): FollowPoint => new FollowPoint(
            $lineDistance->pointAt($metres),
            self::headingAt($lineDistance, $metres),
        ), $at);
    }

    /**
     * Rides the line along the roads. With a start, the ride goes from the
     * start to the line, along it, and home by other roads where it can.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}|null  $start
     */
    public static function ride(LineFollower $lineFollower, RoutingEngine $routingEngine, RouteQuery $routeQuery, array $line, ?array $start = null): GeneratedRoute
    {
        $generatedRoute = self::along($lineFollower, $routeQuery, $line);

        if ($start === null) {
            return $generatedRoute;
        }

        $first = $generatedRoute->coordinates[0] ?? $start;
        $last = $generatedRoute->coordinates[count($generatedRoute->coordinates) - 1] ?? $start;
        $lead = Distance::metres($start, $first) > 30.0 ? $routingEngine->trace($routeQuery, [$start, $first]) : null;
        $coordinates = $lead instanceof GeneratedRoute ? array_merge($lead->coordinates, array_slice($generatedRoute->coordinates, 1)) : $generatedRoute->coordinates;
        $tail = Distance::metres($last, $start) > 30.0 ? $routingEngine->closeLoop($routeQuery, $coordinates, $last, $start) : null;

        if ($tail instanceof GeneratedRoute) {
            $coordinates = array_merge($coordinates, array_slice($tail->coordinates, 1));
        }

        return new GeneratedRoute(
            $routeQuery,
            $coordinates,
            ($lead instanceof GeneratedRoute ? $lead->distanceMetres : 0) + $generatedRoute->distanceMetres + ($tail instanceof GeneratedRoute ? $tail->distanceMetres : 0),
            ($lead instanceof GeneratedRoute ? $lead->ascentMetres : 0) + $generatedRoute->ascentMetres + ($tail instanceof GeneratedRoute ? $tail->ascentMetres : 0),
        );
    }

    /**
     * Follows the line itself. The whole line snapped onto the roads at once
     * is kept when it covers the line and turns back on itself no more than
     * the file does. Otherwise the line is drawn through points along it,
     * then with the points half a step along, then without headings, and
     * the cleanest line that came back is kept.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    public static function along(LineFollower $lineFollower, RouteQuery $routeQuery, array $line): GeneratedRoute
    {
        $allowed = RouteCleanliness::reverseShare($line) + self::DOUBLING_SLACK;
        $candidates = [];
        $failure = null;

        try {
            $matched = $lineFollower->match($routeQuery, $line);
        } catch (RuntimeException) {
            $matched = null;
        }

        if ($matched instanceof GeneratedRoute && self::covers($matched, $line)) {
            if (RouteCleanliness::reverseShare($matched->coordinates) <= $allowed) {
                return $matched;
            }

            $candidates[] = $matched;
        }

        $attempts = [
            self::points($line),
            self::points($line, 0.5),
            array_map(fn (FollowPoint $followPoint): FollowPoint => new FollowPoint($followPoint->point), self::points($line)),
        ];

        foreach ($attempts as $attempt) {
            try {
                $generatedRoute = self::follow($lineFollower, $routeQuery, $attempt);
            } catch (RuntimeException $runtimeException) {
                $failure = $runtimeException;

                continue;
            }

            if (RouteCleanliness::reverseShare($generatedRoute->coordinates) <= $allowed) {
                return $generatedRoute;
            }

            $candidates[] = $generatedRoute;
        }

        usort($candidates, fn (GeneratedRoute $a, GeneratedRoute $b): int => RouteCleanliness::reverseShare($a->coordinates) <=> RouteCleanliness::reverseShare($b->coordinates));

        return $candidates[0] ?? throw $failure ?? new RuntimeException('The roads along that line did not join up.');
    }

    /**
     * Routes through every point in order, a few dozen at a time, and joins
     * the pieces into one line.
     *
     * @param  list<FollowPoint>  $points
     */
    public static function follow(LineFollower $lineFollower, RouteQuery $routeQuery, array $points): GeneratedRoute
    {
        if (count($points) < 2) {
            throw new InvalidArgumentException('A route needs at least two points.');
        }

        $coordinates = [];
        $metres = 0;
        $ascent = 0;
        $step = self::POINTS_PER_TRACE - 1;

        for ($offset = 0; $offset < count($points) - 1; $offset += $step) {
            $generatedRoute = $lineFollower->follow($routeQuery, array_slice($points, $offset, self::POINTS_PER_TRACE));
            $coordinates = $coordinates === [] ? $generatedRoute->coordinates : array_merge($coordinates, array_slice($generatedRoute->coordinates, 1));
            $metres += $generatedRoute->distanceMetres;
            $ascent += $generatedRoute->ascentMetres;
        }

        return new GeneratedRoute($routeQuery, $coordinates, $metres, $ascent);
    }

    /**
     * A snapped line covers the file when it runs from its start to its end
     * at about its length. A match that stopped part way does not.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    private static function covers(GeneratedRoute $generatedRoute, array $line): bool
    {
        $metres = Distance::along($line);
        $first = $generatedRoute->coordinates[0] ?? null;
        $last = $generatedRoute->coordinates[count($generatedRoute->coordinates) - 1] ?? null;

        return $first !== null
            && $last !== null
            && $generatedRoute->distanceMetres >= $metres * 0.9
            && $generatedRoute->distanceMetres <= $metres * 1.3
            && Distance::metres($first, $line[0]) <= 200.0
            && Distance::metres($last, $line[count($line) - 1]) <= 200.0;
    }

    /**
     * The way the line goes at a point, over a few metres either side.
     */
    private static function headingAt(LineDistance $lineDistance, float $metres): int
    {
        $from = $lineDistance->pointAt(max(0.0, $metres - 20.0));
        $to = $lineDistance->pointAt(min($lineDistance->totalMetres(), $metres + 20.0));
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLon = deg2rad($to[0] - $from[0]);
        $bearing = rad2deg(atan2(sin($dLon) * cos($lat2), cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon)));

        return ((int) round($bearing) + 360) % 360;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}  $point
     */
    private static function nearestIndex(array $line, array $point): int
    {
        $nearest = 0;
        $closest = INF;

        foreach ($line as $index => $candidate) {
            $metres = Distance::metres($candidate, $point);

            if ($metres < $closest) {
                $closest = $metres;
                $nearest = $index;
            }
        }

        return $nearest;
    }
}
