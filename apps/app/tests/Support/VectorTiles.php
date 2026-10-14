<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Writes a small Mapbox Vector Tile, so a test can hand the tile reader
 * what OpenFreeMap would. A feature is a point (`at`), several points
 * (`points`), or a line (`line`), each placed by its share of the tile,
 * from 0 to 1, with its tags.
 */
final class VectorTiles
{
    /**
     * @param  array<string, list<array{at?: array{0: float, 1: float}, points?: list<array{0: float, 1: float}>, line?: list<array{0: float, 1: float}>, tags: array<string, string|int>}>>  $layers
     */
    public static function tile(array $layers, int $extent = 4096): string
    {
        $tile = '';

        foreach ($layers as $name => $points) {
            $tile .= self::field(3, self::layer($name, $points, $extent));
        }

        return $tile;
    }

    /**
     * @param  list<array{at?: array{0: float, 1: float}, points?: list<array{0: float, 1: float}>, line?: list<array{0: float, 1: float}>, tags: array<string, string|int>}>  $points
     */
    private static function layer(string $name, array $points, int $extent): string
    {
        $keys = [];
        $values = [];
        $features = '';

        foreach ($points as $point) {
            $tags = [];

            foreach ($point['tags'] as $key => $value) {
                $keys[$key] ??= count($keys);
                $valueKey = gettype($value).':'.$value;
                $values[$valueKey] ??= ['index' => count($values), 'value' => $value];
                $tags[] = $keys[$key];
                $tags[] = $values[$valueKey]['index'];
            }

            $line = $point['line'] ?? null;
            $at = $line ?? $point['points'] ?? [$point['at'] ?? [0.0, 0.0]];
            $geometry = [];
            $cursor = [0, 0];

            foreach ($at as $index => [$across, $down]) {
                if ($index === 0) {
                    $geometry[] = (($line === null ? count($at) : 1) << 3) | 1;
                } elseif ($line !== null && $index === 1) {
                    $geometry[] = ((count($at) - 1) << 3) | 2;
                }

                $x = (int) round($across * $extent);
                $y = (int) round($down * $extent);
                $geometry[] = self::zigzag($x - $cursor[0]);
                $geometry[] = self::zigzag($y - $cursor[1]);
                $cursor = [$x, $y];
            }

            $type = $line === null ? 1 : 2;
            $features .= self::field(2, self::varintField(3, $type).self::field(2, self::packed($tags)).self::field(4, self::packed($geometry)));
        }

        $layer = self::varintField(15, 2).self::field(1, $name).$features;

        foreach (array_keys($keys) as $key) {
            $layer .= self::field(3, $key);
        }

        foreach ($values as ['value' => $value]) {
            $layer .= self::field(4, is_int($value) ? self::varintField(4, $value) : self::field(1, $value));
        }

        return $layer.self::varintField(5, $extent);
    }

    private static function field(int $number, string $bytes): string
    {
        return self::varint(($number << 3) | 2).self::varint(strlen($bytes)).$bytes;
    }

    private static function varintField(int $number, int $value): string
    {
        return self::varint($number << 3).self::varint($value);
    }

    /**
     * @param  list<int>  $numbers
     */
    private static function packed(array $numbers): string
    {
        return implode('', array_map(self::varint(...), $numbers));
    }

    private static function varint(int $value): string
    {
        $bytes = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $bytes .= chr($value > 0 ? $byte | 0x80 : $byte);
        } while ($value > 0);

        return $bytes;
    }

    private static function zigzag(int $value): int
    {
        return ($value << 1) ^ ($value >> 63);
    }
}
