<?php

declare(strict_types=1);

namespace App\Integrations\OpenFreeMap;

use RuntimeException;

/**
 * Reads the points of one layer out of a Mapbox Vector Tile. It knows just
 * enough of the format for that: the layer's name, its keys and values,
 * and each feature's tags and points. Everything else is skipped.
 *
 * @see https://github.com/mapbox/vector-tile-spec/tree/master/2.1
 */
final readonly class VectorTile
{
    private const int MOVE_TO = 1;

    private const int CLOSE_PATH = 7;

    /** A real tile unpacks to well under a megabyte. */
    private const int MAX_INFLATED_BYTES = 8 << 20;

    /**
     * @return list<array{tags: array<string, string|int|float|bool>, points: list<array{0: float, 1: float}>}>
     *                                                                                                          Each point as a share of the tile, from 0 to 1, left to right and top to bottom.
     */
    public static function points(string $bytes, string $layer): array
    {
        if (str_starts_with($bytes, "\x1f\x8b")) {
            // A packed file ends with its unpacked size. The limit on the
            // unpacking still holds if that size is not true.
            $size = strlen($bytes) >= 18 ? unpack('V', substr($bytes, -4)) : false;
            throw_if(! is_array($size) || ! is_int($size[1] ?? null) || $size[1] > self::MAX_INFLATED_BYTES, RuntimeException::class, 'The map tile could not be unpacked.');
            $inflated = gzdecode($bytes, self::MAX_INFLATED_BYTES);
            throw_if($inflated === false, RuntimeException::class, 'The map tile could not be unpacked.');
            $bytes = $inflated;
        }

        foreach (self::fields($bytes) as [$field, $value]) {
            if ($field === 3 && is_string($value)) {
                $points = self::layer($value, $layer);

                if ($points !== null) {
                    return $points;
                }
            }
        }

        return [];
    }

    /**
     * @return list<array{tags: array<string, string|int|float|bool>, points: list<array{0: float, 1: float}>}>|null
     */
    private static function layer(string $bytes, string $wanted): ?array
    {
        $name = null;
        $features = [];
        $keys = [];
        $values = [];
        $extent = 4096;

        foreach (self::fields($bytes) as [$field, $value]) {
            match (true) {
                $field === 1 && is_string($value) => $name = $value,
                $field === 2 && is_string($value) => $features[] = $value,
                $field === 3 && is_string($value) => $keys[] = $value,
                $field === 4 && is_string($value) => $values[] = $value,
                $field === 5 && is_int($value) && $value > 0 => $extent = $value,
                default => null,
            };

            if ($name !== null && $name !== $wanted) {
                return null;
            }
        }

        if ($name !== $wanted) {
            return null;
        }

        $values = array_map(self::value(...), $values);
        $points = [];

        foreach ($features as $feature) {
            $points[] = self::feature($feature, $keys, $values, $extent);
        }

        return $points;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string|int|float|bool|null>  $values
     * @return array{tags: array<string, string|int|float|bool>, points: list<array{0: float, 1: float}>}
     */
    private static function feature(string $bytes, array $keys, array $values, int $extent): array
    {
        $tags = [];
        $points = [];

        foreach (self::fields($bytes) as [$field, $value]) {
            if ($field === 2 && is_string($value)) {
                $pairs = self::packed($value);
                $counter = count($pairs);

                for ($index = 0; $index + 1 < $counter; $index += 2) {
                    $key = $keys[$pairs[$index]] ?? null;
                    $tag = $values[$pairs[$index + 1]] ?? null;

                    if ($key !== null && $tag !== null) {
                        $tags[$key] = $tag;
                    }
                }
            }

            if ($field === 4 && is_string($value)) {
                $points = self::geometry(self::packed($value), $extent);
            }
        }

        return ['tags' => $tags, 'points' => $points];
    }

    /**
     * The points a feature moves to. Lines and shapes also draw; a point
     * layer never does.
     *
     * @param  list<int>  $commands
     * @return list<array{0: float, 1: float}>
     */
    private static function geometry(array $commands, int $extent): array
    {
        $points = [];
        $x = 0;
        $y = 0;
        $index = 0;
        $count = count($commands);

        while ($index < $count) {
            $command = $commands[$index++];
            $id = $command & 7;

            if ($id === self::CLOSE_PATH) {
                continue;
            }

            for ($step = 0; $step < $command >> 3; $step++) {
                if (! isset($commands[$index], $commands[$index + 1])) {
                    return $points;
                }

                $x += self::zigzag($commands[$index++]);
                $y += self::zigzag($commands[$index++]);

                if ($id === self::MOVE_TO) {
                    $points[] = [$x / $extent, $y / $extent];
                }
            }
        }

        return $points;
    }

    private static function value(string $bytes): string|int|float|bool|null
    {
        foreach (self::fields($bytes) as [$field, $value]) {
            return match (true) {
                $field === 1 && is_string($value) => $value,
                $field === 2 && is_string($value) && strlen($value) === 4 => self::float32($value),
                $field === 3 && is_string($value) && strlen($value) === 8 => self::float64($value),
                ($field === 4 || $field === 5) && is_int($value) => $value,
                $field === 6 && is_int($value) => self::zigzag($value),
                $field === 7 && is_int($value) => $value !== 0,
                default => null,
            };
        }

        return null;
    }

    /**
     * Each field of a message, in order: its number, and an integer for a
     * varint or the raw bytes for anything else.
     *
     * @return iterable<array{0: int, 1: int|string}>
     */
    private static function fields(string $bytes): iterable
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $key = self::varint($bytes, $offset);
            $field = $key >> 3;

            yield match ($key & 7) {
                0 => [$field, self::varint($bytes, $offset)],
                1 => [$field, self::take($bytes, $offset, 8)],
                2 => [$field, self::take($bytes, $offset, self::varint($bytes, $offset))],
                5 => [$field, self::take($bytes, $offset, 4)],
                default => throw new RuntimeException('The map tile is not in a format Traceway reads.'),
            };
        }
    }

    /**
     * @return list<int>
     */
    private static function packed(string $bytes): array
    {
        $numbers = [];
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $numbers[] = self::varint($bytes, $offset);
        }

        return $numbers;
    }

    private static function varint(string $bytes, int &$offset): int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($bytes);

        do {
            throw_if($offset >= $length || $shift > 63, RuntimeException::class, 'The map tile ends too soon.');
            $byte = ord($bytes[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0);

        return $result;
    }

    private static function take(string $bytes, int &$offset, int $length): string
    {
        throw_if($length < 0 || $offset + $length > strlen($bytes), RuntimeException::class, 'The map tile ends too soon.');
        $taken = substr($bytes, $offset, $length);
        $offset += $length;

        return $taken;
    }

    private static function zigzag(int $value): int
    {
        return ($value >> 1) ^ -($value & 1);
    }

    private static function float32(string $bytes): float
    {
        $unpacked = unpack('g', $bytes);

        return is_array($unpacked) && is_float($unpacked[1] ?? null) ? $unpacked[1] : 0.0;
    }

    private static function float64(string $bytes): float
    {
        $unpacked = unpack('e', $bytes);

        return is_array($unpacked) && is_float($unpacked[1] ?? null) ? $unpacked[1] : 0.0;
    }
}
