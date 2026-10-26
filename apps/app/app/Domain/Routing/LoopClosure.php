<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Ways to ride back to the pin without turning around on the road just used.
 */
final class LoopClosure
{
    /**
     * @param  list<array{0: float, 1: float}>  $ridden
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @return list<array{waypoints: list<array{0: float, 1: float}>, heading: int, via_radius?: int}>
     */
    public static function plans(array $ridden, array $from, array $to, int $targetMetres = 0): array
    {
        $arrival = self::arrivalBearing($ridden, $from);
        $gap = self::metres($from, $to);
        $riddenMetres = self::length($ridden);
        $remaining = $targetMetres > $riddenMetres + 1_000
            ? (float) ($targetMetres - $riddenMetres)
            : $gap;
        $plans = [];

        foreach ([0.4, 0.62, 0.9] as $scale) {
            $radius = max(700.0, min($remaining * $scale, 25_000.0));
            $viaRadius = (int) max(250, min(1_200, $radius * 0.28));

            foreach ([70.0, 120.0, 240.0, 290.0] as $offset) {
                $heading = fmod($arrival + $offset, 360.0);
                $plans[] = [
                    'waypoints' => [
                        $from,
                        self::offset($from[1], $from[0], $radius, $heading),
                        $to,
                    ],
                    'heading' => (int) round($heading) % 360,
                    'via_radius' => $viaRadius,
                ];
            }
        }

        $plans[] = [
            'waypoints' => [$from, $to],
            'heading' => (int) round(fmod($arrival + 90.0, 360.0)) % 360,
        ];

        return $plans;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ridden
     * @param  list<GeneratedRoute>  $routes
     */
    public static function cleanest(array $ridden, array $routes, int $targetMetres = 0): ?GeneratedRoute
    {
        $best = null;
        $bestScore = INF;
        $riddenLength = self::length($ridden);

        foreach ($routes as $route) {
            $closing = RouteCleanliness::withoutSpurs($route->coordinates);
            $anchors = [];
            $start = $route->coordinates[0] ?? null;
            $end = array_last($route->coordinates) ?? null;

            if ($start !== null) {
                $anchors[] = $start;
            }

            if ($end !== null) {
                $anchors[] = $end;
            }

            if (count($closing) < 2 || ($anchors !== [] && ! RouteCleanliness::visits($closing, $anchors))) {
                $closing = $route->coordinates;
            }

            $combined = array_merge($ridden, array_slice($closing, 1));
            $doubledMetres = RouteCleanliness::doubledMetres($combined);
            $closingLength = self::length($closing);
            $target = $targetMetres > 0 ? $targetMetres : $riddenLength + $closingLength;
            $error = abs(($riddenLength + $closingLength) - $target);
            // A way home back down a road already ridden, past the start
            // street, loses to any other. Each metre counts on top, so when
            // the ridden part already doubles, the closing that adds least wins.
            $doubled = ($doubledMetres > RouteCleanliness::doubledLimit($riddenLength + $closingLength) ? 1_000_000 : 0) + (int) round($doubledMetres * 2);
            $score = $doubled + $error;

            if ($score >= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $best = new GeneratedRoute(
                query: $route->query,
                coordinates: $closing,
                distanceMetres: self::length($closing),
                ascentMetres: $route->ascentMetres,
            );
        }

        return $best;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ridden
     * @param  array{0: float, 1: float}  $from
     */
    private static function arrivalBearing(array $ridden, array $from): float
    {
        $end = array_last($ridden) ?? $from;
        $anchor = $end;
        $travelled = 0.0;

        for ($index = count($ridden) - 1; $index >= 1; $index--) {
            $previous = $ridden[$index - 1] ?? null;
            $current = $ridden[$index] ?? null;

            if ($previous === null || $current === null) {
                continue;
            }

            $travelled += self::metres($previous, $current);
            $anchor = $previous;

            if ($travelled >= 150.0) {
                break;
            }
        }

        return self::bearing($anchor, $end);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function metres(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($to[0] - $from[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private static function length(array $coordinates): int
    {
        $metres = 0.0;

        for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
            $from = $coordinates[$index - 1] ?? null;
            $to = $coordinates[$index] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $metres += self::metres($from, $to);
        }

        return (int) round($metres);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function bearing(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLon = deg2rad($to[0] - $from[0]);
        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
        $degrees = rad2deg(atan2($y, $x));

        return fmod($degrees + 360.0, 360.0);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function offset(float $latitude, float $longitude, float $distanceMetres, float $bearingDegrees): array
    {
        $angular = $distanceMetres / 6_371_000;
        $bearing = deg2rad($bearingDegrees);
        $lat = deg2rad($latitude);
        $lon = deg2rad($longitude);
        $lat2 = asin(sin($lat) * cos($angular) + cos($lat) * sin($angular) * cos($bearing));
        $lon2 = $lon + atan2(
            sin($bearing) * sin($angular) * cos($lat),
            cos($angular) - sin($lat) * sin($lat2),
        );

        return [rad2deg($lon2), rad2deg($lat2)];
    }
}
