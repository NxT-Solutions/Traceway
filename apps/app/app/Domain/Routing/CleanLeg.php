<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Picks the leg that does not turn around on a road already used, by this
 * leg or by the legs before it. It is held to the same limit as a loop: 1 km
 * past its start street, or 12% of a short leg. Among clean legs, the one
 * nearest the length wins.
 */
final class CleanLeg
{
    /**
     * @param  list<array{0: float, 1: float}>  $ridden  The legs already planned, joined.
     * @param  list<GeneratedRoute>  $routes
     */
    public static function best(array $ridden, array $routes, int $targetMetres): ?GeneratedRoute
    {
        $best = null;
        $bestScore = INF;

        foreach ($routes as $route) {
            $score = self::score($ridden, $route, $targetMetres);

            if ($score < $bestScore) {
                $best = $route;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Doubling back costs far more than being off the length. A leg that
     * turns around loses to any clean leg, however far off its length.
     * When every leg turns around, the least doubled road wins. Among clean
     * legs the doubled metres cost what they cost a loop.
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    public static function score(array $ridden, GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        $doubled = self::doubledMetres($ridden, $generatedRoute);
        $error = abs($generatedRoute->distanceMetres - $targetMetres) / max(1, $targetMetres);

        if (self::over($doubled, $generatedRoute)) {
            return 1_000 + ($doubled / 1_000) + $error;
        }

        return RouteCleanliness::doubledCost($doubled, Distance::along($generatedRoute->coordinates)) + $error;
    }

    /**
     * Whether this leg rides back down a road, its own or one a leg before
     * it used, past the street the leg starts on.
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    public static function doublesBack(array $ridden, GeneratedRoute $generatedRoute): bool
    {
        return self::over(self::doubledMetres($ridden, $generatedRoute), $generatedRoute);
    }

    /**
     * The metres of this leg that ride back over a road it, or a leg before
     * it, already used. The street the leg starts on, the transition or the
     * handover, may be ridden both ways, and so may a loop leg's ride home.
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    public static function doubledMetres(array $ridden, GeneratedRoute $generatedRoute): float
    {
        return RouteCleanliness::doubledMetres($generatedRoute->coordinates, before: $ridden);
    }

    private static function over(float $doubled, GeneratedRoute $generatedRoute): bool
    {
        return $doubled > RouteCleanliness::doubledLimit(Distance::along($generatedRoute->coordinates));
    }

    /**
     * Cuts the stub where a detour drives to its point and straight back,
     * as long as the leg still starts and ends where it should.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    public static function withoutSpurs(GeneratedRoute $generatedRoute, array $from, array $to): GeneratedRoute
    {
        $trimmed = RouteCleanliness::withoutSpurs($generatedRoute->coordinates);
        $before = Distance::along($generatedRoute->coordinates);

        if (count($trimmed) < 2 || $before <= 0 || ! RouteCleanliness::visits($trimmed, [$from, $to])) {
            return $generatedRoute;
        }

        $after = Distance::along($trimmed);

        if ($after >= $before) {
            return $generatedRoute;
        }

        return new GeneratedRoute(
            $generatedRoute->query,
            $trimmed,
            (int) round($generatedRoute->distanceMetres * $after / $before),
            $generatedRoute->ascentMetres,
        );
    }
}
