<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Splits the waypoints of one route into pieces a routing server will
 * take. A server can refuse a request whose points span more than its
 * limit in a straight line, such as a 180 km ride, or one with more points
 * than it takes at once. Each piece stays under both limits, and each one
 * starts where the last one ended, so the pieces join into the same route.
 */
final class WaypointPieces
{
    /**
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function split(array $waypoints, float $maxMetres, int $maxPoints = PHP_INT_MAX): array
    {
        $maxPoints = max(2, $maxPoints);

        $points = self::withShortHops($waypoints, $maxMetres * 0.9);
        $first = $points[0] ?? null;

        if ($first === null) {
            return [];
        }

        $pieces = [];
        $piece = [$first];
        $span = 0.0;

        foreach (array_slice($points, 1) as $point) {
            $last = $piece[count($piece) - 1] ?? $first;
            $hop = Distance::metres($last, $point);

            if (count($piece) >= 2 && ($span + $hop > $maxMetres || count($piece) >= $maxPoints)) {
                $pieces[] = $piece;
                $piece = [$last];
                $span = 0.0;
            }

            $piece[] = $point;
            $span += $hop;
        }

        $pieces[] = $piece;

        return $pieces;
    }

    /**
     * Adds points along any single hop longer than the limit, so no piece
     * has to hold more than one.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<array{0: float, 1: float}>
     */
    private static function withShortHops(array $waypoints, float $longestHop): array
    {
        $points = [];
        $previous = null;

        foreach ($waypoints as $waypoint) {
            if ($previous !== null) {
                $parts = (int) ceil(Distance::metres($previous, $waypoint) / max(1.0, $longestHop));

                for ($part = 1; $part < $parts; $part++) {
                    $share = $part / $parts;
                    $points[] = [
                        $previous[0] + ($waypoint[0] - $previous[0]) * $share,
                        $previous[1] + ($waypoint[1] - $previous[1]) * $share,
                    ];
                }
            }

            $points[] = $waypoint;
            $previous = $waypoint;
        }

        return $points;
    }
}
