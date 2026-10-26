<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Picks the leg that does not turn around on a road already used, by this
 * leg or by the legs before it. Among clean legs, the one nearest the length
 * wins. A leg is judged by its share of doubled road, not by the metres a
 * loop is held to: a leg ends at a handover, so it has no ride home.
 */
final class CleanLeg
{
    /** Above this share of doubled-back metres, a leg counts as turning around. */
    public const float DOUBLED = 0.12;

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
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    public static function score(array $ridden, GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        $share = self::doubledShare($ridden, $generatedRoute);
        $error = abs($generatedRoute->distanceMetres - $targetMetres) / max(1, $targetMetres);

        return $share > self::DOUBLED ? 1_000 + $share + $error : $share * 2 + $error;
    }

    /**
     * The share of this leg that runs back over a road it, or a leg before
     * it, already used.
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     */
    public static function doubledShare(array $ridden, GeneratedRoute $generatedRoute): float
    {
        $leg = $generatedRoute->coordinates;

        if ($ridden === []) {
            return RouteCleanliness::reverseShare($leg);
        }

        $before = RouteCleanliness::reverseShare($ridden);
        $riddenMetres = Distance::along($ridden);
        $legMetres = Distance::along($leg);
        $joined = RouteCleanliness::reverseShare(array_merge($ridden, array_slice($leg, 1)));
        $added = $joined * ($riddenMetres + $legMetres) - $before * $riddenMetres;

        return $legMetres > 0 ? max(0.0, $added / $legMetres) : 0.0;
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
