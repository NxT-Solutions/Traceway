<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Drops the points of a line that sit within a few metres of the straight
 * stretch between the points kept either side of them (Ramer, Douglas, and
 * Peucker). The first and the last point always stay, so the line still
 * starts and ends where it did.
 */
final class LineSimplifier
{
    /** For a line the rider follows: no change on the map or on a watch. */
    public const float ROUTE_METRES = 1.0;

    /** For a thumbnail or an overview map of many routes. */
    public const float OVERVIEW_METRES = 12.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<array{0: float, 1: float}>
     */
    public static function simplify(array $line, float $toleranceMetres): array
    {
        $count = count($line);

        if ($count < 3 || $toleranceMetres <= 0.0) {
            return $line;
        }

        $first = $line[0];
        $metresPerLongitude = self::METRES_PER_DEGREE * cos(deg2rad($first[1]));
        $points = array_map(
            fn (array $point): array => [$point[0] * $metresPerLongitude, $point[1] * self::METRES_PER_DEGREE],
            $line,
        );

        $keep = array_fill(0, $count, value: false);
        $keep[0] = true;
        $keep[$count - 1] = true;
        $spans = [[0, $count - 1]];

        while ($spans !== []) {
            [$start, $end] = array_pop($spans);
            $farthest = 0.0;
            $farIndex = null;

            for ($index = $start + 1; $index < $end; $index++) {
                $gap = self::gap($points[$index], $points[$start], $points[$end]);

                if ($gap > $farthest) {
                    $farthest = $gap;
                    $farIndex = $index;
                }
            }

            if ($farIndex !== null && $farthest > $toleranceMetres) {
                $keep[$farIndex] = true;
                $spans[] = [$start, $farIndex];
                $spans[] = [$farIndex, $end];
            }
        }

        $kept = [];

        foreach ($line as $index => $point) {
            if ($keep[$index]) {
                $kept[] = $point;
            }
        }

        return $kept;
    }

    /**
     * Metres from a point to the stretch between two others, on a flat
     * projection, which holds within a stretch of a route.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     */
    private static function gap(array $point, array $start, array $end): float
    {
        $dx = $end[0] - $start[0];
        $dy = $end[1] - $start[1];
        $lengthSquared = $dx * $dx + $dy * $dy;

        if ($lengthSquared === 0.0) {
            return hypot($point[0] - $start[0], $point[1] - $start[1]);
        }

        $share = max(0.0, min(1.0, (($point[0] - $start[0]) * $dx + ($point[1] - $start[1]) * $dy) / $lengthSquared));

        return hypot($point[0] - ($start[0] + $share * $dx), $point[1] - ($start[1] + $share * $dy));
    }
}
