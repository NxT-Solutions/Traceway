<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Great-circle distance between two [lon, lat] points.
 */
final class Distance
{
    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    public static function metres(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($to[0] - $from[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    public static function along(array $line): float
    {
        $metres = 0.0;
        $previous = null;

        foreach ($line as $point) {
            if ($previous !== null) {
                $metres += self::metres($previous, $point);
            }

            $previous = $point;
        }

        return $metres;
    }
}
