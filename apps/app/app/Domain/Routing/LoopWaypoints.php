<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Three bearings around a start pin, then back. The routing engine
 * follows roads between these points so the result is a loop.
 */
final class LoopWaypoints
{
    private const float EARTH_RADIUS_METRES = 6_371_000.0;

    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function around(
        float $latitude,
        float $longitude,
        int $distanceMetres,
        float $bearingOffset = 0.0,
        ?float $viaLatitude = null,
        ?float $viaLongitude = null,
        float $radiusScale = 0.55,
        int $stops = 3,
        bool $offsetCenter = false,
    ): array {
        // Roads run longer than the straight chords of this circle, so the
        // radius is pulled in. Otherwise a 20 km request comes back near 40 km.
        $radius = $distanceMetres / (2 * M_PI) * $radiusScale;
        $centerLatitude = $latitude;
        $centerLongitude = $longitude;

        if ($offsetCenter) {
            [$centerLongitude, $centerLatitude] = self::offset($latitude, $longitude, $radius * 0.45, $bearingOffset);
        }

        $ring = [];

        for ($index = 0; $index < $stops; $index++) {
            $ring[] = self::offset(
                $centerLatitude,
                $centerLongitude,
                $radius,
                fmod($bearingOffset + ($index * (360 / $stops)), 360.0),
            );
        }

        if ($offsetCenter) {
            $nearest = self::nearest($ring, $longitude, $latitude);
            unset($ring[$nearest]);
            $ring = array_values($ring);
        }

        if ($viaLatitude !== null && $viaLongitude !== null && $ring !== []) {
            $ring[self::nearest($ring, $viaLongitude, $viaLatitude)] = [$viaLongitude, $viaLatitude];
        }

        return [
            [$longitude, $latitude],
            ...$ring,
            [$longitude, $latitude],
        ];
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function outAndBack(
        float $latitude,
        float $longitude,
        int $distanceMetres,
        ?float $viaLatitude = null,
        ?float $viaLongitude = null,
    ): array {
        $far = $viaLatitude !== null && $viaLongitude !== null
            ? [$viaLongitude, $viaLatitude]
            : self::offset($latitude, $longitude, $distanceMetres / 2, 45.0);

        return [[$longitude, $latitude], $far, [$longitude, $latitude]];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function offset(float $latitude, float $longitude, float $distanceMetres, float $bearingDegrees): array
    {
        $angular = $distanceMetres / self::EARTH_RADIUS_METRES;
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

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function nearest(array $points, float $longitude, float $latitude): int
    {
        $nearest = 0;
        $nearestDistance = INF;

        foreach ($points as $index => $point) {
            $distance = (($point[0] - $longitude) ** 2) + (($point[1] - $latitude) ** 2);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $index;
            }
        }

        return $nearest;
    }
}
