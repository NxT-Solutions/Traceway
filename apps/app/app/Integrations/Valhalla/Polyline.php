<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

final class Polyline
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function encode(array $coordinates, int $precision = 6): string
    {
        $factor = 10 ** $precision;
        $encoded = '';
        $previousLat = 0;
        $previousLon = 0;

        foreach ($coordinates as $coordinate) {
            $lat = (int) round($coordinate[1] * $factor);
            $lon = (int) round($coordinate[0] * $factor);
            $encoded .= self::chunk($lat - $previousLat);
            $encoded .= self::chunk($lon - $previousLon);
            $previousLat = $lat;
            $previousLon = $lon;
        }

        return $encoded;
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function decode(string $encoded, int $precision = 6): array
    {
        $factor = 10 ** $precision;
        $length = strlen($encoded);
        $index = 0;
        $latitude = 0;
        $longitude = 0;
        $coordinates = [];

        while ($index < $length) {
            $latitude += self::read($encoded, $index);
            $longitude += self::read($encoded, $index);
            $coordinates[] = [$longitude / $factor, $latitude / $factor];
        }

        return $coordinates;
    }

    private static function chunk(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $chunk = '';

        while ($value >= 0x20) {
            $chunk .= self::byte((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        return $chunk.self::byte($value + 63);
    }

    private static function byte(int $value): string
    {
        return chr($value & 0xFF);
    }

    private static function read(string $encoded, int &$index): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = ord($encoded[$index]) - 63;
            $index++;
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) !== 0 ? ~($result >> 1) : ($result >> 1);
    }
}
