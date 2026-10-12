<?php

declare(strict_types=1);

namespace App\Queries;

use App\Routes\SavedLeg;
use RuntimeException;

/**
 * The query builder returns JSON columns as text. Screens need the list.
 */
final class JsonColumn
{
    /**
     * @return list<array{0: float, 1: float}>
     */
    public static function line(mixed $value): array
    {
        $decoded = self::decode($value);
        $line = [];

        foreach ($decoded as $point) {
            throw_if(! is_array($point) || ! array_key_exists(0, $point) || ! array_key_exists(1, $point), RuntimeException::class, 'A route point is missing a coordinate.');

            $longitude = $point[0];
            $latitude = $point[1];

            throw_if(! is_numeric($longitude) || ! is_numeric($latitude), RuntimeException::class, 'A route point is not numeric.');

            $line[] = [(float) $longitude, (float) $latitude];
        }

        return $line;
    }

    /**
     * @return list<array{0: float, 1: float}>|null
     */
    public static function optionalLine(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return self::line($value);
    }

    /**
     * The legs of a multisport plan, or none for a single sport.
     *
     * @return list<SavedLeg>
     */
    public static function legs(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $legs = [];

        foreach (self::decode($value) as $leg) {
            throw_if(! is_array($leg), RuntimeException::class, 'A leg is not a record.');

            $discipline = $leg['discipline'] ?? null;
            $distance = $leg['distance_metres'] ?? null;
            $ascent = $leg['ascent_metres'] ?? null;

            throw_if(! is_string($discipline) || ! is_numeric($distance) || ! is_numeric($ascent), RuntimeException::class, 'A leg is missing a value.');

            $color = $leg['color'] ?? null;

            $legs[] = SavedLeg::from($discipline, (int) $distance, (int) $ascent, self::line($leg['coordinates'] ?? null), is_string($color) ? $color : null);
        }

        return $legs;
    }

    /**
     * @return list<mixed>
     */
    private static function decode(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, associative: true);

            throw_if(! is_array($decoded) || ! array_is_list($decoded), RuntimeException::class, 'A route line is not a list.');

            return $decoded;
        }

        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        throw new RuntimeException('A route line is not a list.');
    }
}
