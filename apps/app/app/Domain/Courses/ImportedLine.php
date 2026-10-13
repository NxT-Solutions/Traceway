<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Distance;
use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineDistance;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use InvalidArgumentException;

/**
 * Rides an imported line again: the other way round, or from the rider's own
 * door. The new line comes from the routing engine, through points picked
 * along the old one, so it follows real roads for this sport.
 */
final class ImportedLine
{
    /** A line that ends this close to its start is a loop. */
    public const float LOOP_METRES = 300.0;

    /** A routing server takes this many points in one request. */
    public const int POINTS_PER_TRACE = 40;

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
     * The points to route through for the line ridden backwards.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    public static function reversed(array $line): array
    {
        return self::waypoints(array_reverse($line));
    }

    /**
     * The points to route through for the line ridden from a start of the
     * rider's choosing. A loop begins where it passes closest to the start.
     * Any other line is ridden from its own start. Either way the ride goes
     * from the start to the line, along it, and home again.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}  $start
     * @return list<array{0: float, 1: float}>
     */
    public static function fromStart(array $line, array $start): array
    {
        if (self::isLoop($line)) {
            $nearest = self::nearestIndex($line, $start);
            $line = array_merge(array_slice($line, $nearest), array_slice($line, 1, $nearest));
        }

        $waypoints = self::waypoints($line);
        $first = $waypoints[0];
        $last = $waypoints[count($waypoints) - 1];

        if (Distance::metres($start, $first) > 30.0) {
            array_unshift($waypoints, $start);
        } else {
            $waypoints[0] = $start;
        }

        if (Distance::metres($start, $last) > 30.0) {
            $waypoints[] = $start;
        } else {
            $waypoints[count($waypoints) - 1] = $start;
        }

        return $waypoints;
    }

    /**
     * Points along the line, close enough that the new line keeps to the old
     * roads, and few enough that a long line stays quick to route.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    public static function waypoints(array $line): array
    {
        $lineDistance = LineDistance::of($line);
        $total = $lineDistance->totalMetres();
        $spacing = max(750.0, $total / 200);
        $count = max(1, (int) ceil($total / $spacing));
        $waypoints = [];

        for ($index = 0; $index <= $count; $index++) {
            $waypoints[] = $lineDistance->pointAt($total * $index / $count);
        }

        return $waypoints;
    }

    /**
     * Routes through every point in order, a few dozen at a time, and joins
     * the pieces into one line.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public static function trace(RoutingEngine $routingEngine, RouteQuery $routeQuery, array $waypoints): GeneratedRoute
    {
        if (count($waypoints) < 2) {
            throw new InvalidArgumentException('A route needs at least two points.');
        }

        $coordinates = [];
        $metres = 0;
        $ascent = 0;
        $step = self::POINTS_PER_TRACE - 1;

        for ($offset = 0; $offset < count($waypoints) - 1; $offset += $step) {
            $piece = array_slice($waypoints, $offset, self::POINTS_PER_TRACE);
            $generatedRoute = $routingEngine->trace($routeQuery, $piece);
            $coordinates = $coordinates === [] ? $generatedRoute->coordinates : array_merge($coordinates, array_slice($generatedRoute->coordinates, 1));
            $metres += $generatedRoute->distanceMetres;
            $ascent += $generatedRoute->ascentMetres;
        }

        return new GeneratedRoute($routeQuery, $coordinates, $metres, $ascent);
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
