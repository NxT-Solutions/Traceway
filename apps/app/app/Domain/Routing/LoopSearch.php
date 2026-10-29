<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use Closure;
use RuntimeException;

/**
 * How every engine looks for loops, so a change to it reaches them all: the
 * directions it tries, the circle each is tried on, and how a direction that
 * came out off the length is aimed again. Which loops are offered is
 * RouteCleanliness's to say. An engine only draws the lines it is handed.
 */
final class LoopSearch
{
    /** The directions a loop is first tried in, a sixth of a turn apart. */
    private const array BEARINGS = [12.0, 72.0, 132.0, 192.0, 252.0, 312.0];

    /** The size of the first circle each loop is tried on. See LoopWaypoints::around(). */
    private const float FIRST_SCALE = 0.95;

    /** How far round from each of BEARINGS the directions halfway between them are. */
    private const float BETWEEN_DEGREES = 30.0;

    /**
     * Up to three loops, best first. The six directions first; then, when the
     * best is not close to the length or fewer than three are on it, each
     * direction aimed again; then, still short of three, the six directions in
     * between. $rounds stops sooner, for an engine that is slow to ask.
     *
     * $draw gets lists of waypoints and draws each, with null where no road
     * fits. It throws MapServerBusy when the server answered none of them.
     *
     * @param  Closure(list<list<array{0: float, 1: float}>>): list<GeneratedRoute|null>  $draw
     * @return list<GeneratedRoute>
     */
    public static function loops(RouteQuery $routeQuery, Closure $draw, int $rounds = 3): array
    {
        $target = $routeQuery->distanceMetres;
        $tolerance = RouteCleanliness::lengthTolerance($target);
        // A most or a least aims a little inside it. See RouteCleanliness::aimMetres().
        $aimed = RouteCleanliness::aimMetres($routeQuery);
        $firstScale = self::FIRST_SCALE * $aimed / $target;
        $first = self::drawn($routeQuery, $draw, array_fill_keys(array_keys(self::BEARINGS), $firstScale));
        $found = array_values($first);
        $best = RouteCleanliness::cleanest($found, $target);
        $aims = [];

        foreach ($first as $index => $route) {
            $aims[$index] = self::aim($firstScale, $route, $aimed);
        }

        try {
            // The roads one way may wind more than the roads another, so each
            // direction is aimed again on its own: its circle grows or shrinks by
            // how far its loop came out off the length. A direction already close
            // keeps its loop. This runs when the best loop is not close, or when
            // there are not yet three loops on the length to choose from.
            if ($rounds >= 2 && $best instanceof GeneratedRoute && (abs($best->distanceMetres - $aimed) > $tolerance / 2 || count(RouteCleanliness::diverse($found, $target, fill: false)) < 3)) {
                $scales = [];

                foreach ($first as $index => $route) {
                    if (abs($route->distanceMetres - $aimed) > $tolerance / 4) {
                        $scales[$index] = $aims[$index];
                    }
                }

                foreach (self::drawn($routeQuery, $draw, $scales) as $index => $route) {
                    $found[] = $route;
                    $aims[$index] = self::aim($scales[$index] ?? $firstScale, $route, $aimed);
                }
            }

            // Still fewer than three loops on the length: the six directions
            // halfway between are tried too, each on the circle its two
            // neighbours were aimed at.
            if ($rounds >= 3 && $found !== [] && count(RouteCleanliness::diverse($found, $target, fill: false)) < 3) {
                $between = [];

                foreach (array_keys(self::BEARINGS) as $index) {
                    $near = array_values(array_filter([$aims[$index] ?? null, $aims[($index + 1) % count(self::BEARINGS)] ?? null], is_float(...)));
                    $between[$index] = $near === [] ? $firstScale : array_sum($near) / count($near);
                }

                $found = array_merge($found, array_values(self::drawn($routeQuery, $draw, $between, self::BETWEEN_DEGREES)));
            }
        } catch (MapServerBusy) {
            // A server that stops answering partway is not asked again, and
            // the loops it already gave are kept.
        }

        $diverse = RouteCleanliness::diverse($found, $target);

        if ($diverse === []) {
            throw new RuntimeException('No loop matched those filters.');
        }

        return $diverse;
    }

    /**
     * The circle a direction should be tried on to land on the length, from
     * the circle it was tried on and how long its loop came out.
     */
    private static function aim(float $scale, GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        if ($generatedRoute->distanceMetres <= 0) {
            return $scale;
        }

        return min(1.45, max(0.5, $scale * ($targetMetres / $generatedRoute->distanceMetres)));
    }

    /**
     * A loop for each direction given, by its index in BEARINGS turned by
     * $turn degrees and by the rider's shuffle, each on a circle of its own
     * scale.
     *
     * Two points on a circle beside the pin: out to one, across to the other,
     * and home. With three, the roads between them crossed back over each
     * other: around ten Belgian pins, 71% of loops came out a figure of eight,
     * pinched, or back past the pin halfway round, against 12% with two.
     *
     * @param  Closure(list<list<array{0: float, 1: float}>>): list<GeneratedRoute|null>  $draw
     * @param  array<int, float>  $scales
     * @return array<int, GeneratedRoute>
     */
    private static function drawn(RouteQuery $routeQuery, Closure $draw, array $scales, float $turn = 0.0): array
    {
        $plans = [];

        foreach ($scales as $index => $scale) {
            $plans[] = LoopWaypoints::around(
                $routeQuery->startLatitude,
                $routeQuery->startLongitude,
                $routeQuery->distanceMetres,
                fmod((self::BEARINGS[$index] ?? 0.0) + $turn + $routeQuery->turnDegrees, 360.0),
                $routeQuery->viaLatitude,
                $routeQuery->viaLongitude,
                $scale,
                3,
                offsetCenter: true,
            );
        }

        // A direction that does not fit, or that the server did not answer, is skipped.
        return $plans === [] ? [] : array_filter(array_combine(array_keys($scales), $draw($plans)));
    }
}
