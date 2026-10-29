<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * A multisport leg is judged by RouteCleanliness, like every loop, so a rule
 * made there holds for legs too. The one thing a leg adds is the legs before
 * it: riding back down their roads is doubling back as well.
 */
final class CleanLeg
{
    /**
     * The best of the legs offered: clean before flawed, then nearest the
     * length, as RouteCleanliness ranks a loop.
     *
     * @param  list<array{0: float, 1: float}>  $ridden  The legs already planned, joined.
     * @param  list<GeneratedRoute>  $routes
     */
    public static function best(array $ridden, array $routes, int $targetMetres): ?GeneratedRoute
    {
        return RouteCleanliness::cleanest($routes, $targetMetres, $ridden);
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
