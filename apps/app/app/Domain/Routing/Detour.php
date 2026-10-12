<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Where to send a chained leg so it covers a set length between two fixed
 * points. The leg goes through one point off to the side of the straight
 * line, far enough that the way there and on to the finish adds up to the
 * length. The routing engine still draws every metre.
 */
final class Detour
{
    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * One point on each side of the line from start to finish.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @param  float  $roadFactor  How much longer the roads are than a straight line, from the direct route.
     * @return list<array{0: float, 1: float}>
     */
    public static function vias(array $from, array $to, int $targetMetres, float $roadFactor, float $scale = 1.0): array
    {
        $straight = $targetMetres / max(1.0, $roadFactor);
        $gap = Distance::metres($from, $to);
        $metresPerLongitude = self::METRES_PER_DEGREE * cos(deg2rad(($from[1] + $to[1]) / 2));

        if ($gap < 50.0) {
            $reach = $straight / 2 * $scale;

            return [
                self::offset($from, 0.0, $reach, $metresPerLongitude),
                self::offset($from, $reach, 0.0, $metresPerLongitude),
            ];
        }

        $side = sqrt(max(0.0, ($straight / 2) ** 2 - ($gap / 2) ** 2)) * $scale;
        $middle = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];
        $east = ($to[0] - $from[0]) * $metresPerLongitude / $gap;
        $north = ($to[1] - $from[1]) * self::METRES_PER_DEGREE / $gap;

        return [
            self::offset($middle, -$north * $side, $east * $side, $metresPerLongitude),
            self::offset($middle, $north * $side, -$east * $side, $metresPerLongitude),
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @return array{0: float, 1: float}
     */
    private static function offset(array $point, float $eastMetres, float $northMetres, float $metresPerLongitude): array
    {
        $longitude = $point[0] + $eastMetres / max(1.0, $metresPerLongitude);
        $latitude = $point[1] + $northMetres / self::METRES_PER_DEGREE;

        return [
            max(-180.0, min(180.0, $longitude)),
            max(-90.0, min(90.0, $latitude)),
        ];
    }
}
