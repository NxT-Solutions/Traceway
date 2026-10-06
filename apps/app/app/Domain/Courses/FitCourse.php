<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Sport;
use InvalidArgumentException;

/**
 * A Garmin course file. The head unit follows the positions on its own map.
 */
final class FitCourse
{
    private const int EPOCH = 631_065_600;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function encode(string $name, Sport $sport, array $coordinates): string
    {
        if (count($coordinates) < 2) {
            throw new InvalidArgumentException('A course needs at least two points.');
        }

        $label = new DeviceName(mb_substr(trim($name) === '' ? 'Traceway' : trim($name), 0, 15));
        $points = self::thin($coordinates);
        $started = time() - self::EPOCH;
        $distance = self::distanceCentimetres($points);
        $seconds = max(1, (int) round(($distance / 100) / 5.5));

        $data = self::fileId($started)
            .self::courseMessage($label->value, self::sportCode($sport))
            .self::lapMessage($points, $started, $seconds, $distance)
            .self::eventMessage($started, 0)
            .self::records($points, $started, $seconds, $distance)
            .self::eventMessage($started + $seconds, 4);

        $header = self::header(strlen($data));

        return $header.$data.self::crc16($header.$data);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    private static function thin(array $coordinates): array
    {
        $count = count($coordinates);

        if ($count <= 1500) {
            return $coordinates;
        }

        $step = (int) ceil($count / 1500);
        $thinned = [];

        for ($index = 0; $index < $count; $index += $step) {
            $point = $coordinates[$index] ?? null;

            if ($point !== null) {
                $thinned[] = $point;
            }
        }

        $last = $coordinates[$count - 1] ?? null;

        if ($last !== null && ($thinned === [] || $thinned[array_key_last($thinned)] !== $last)) {
            $thinned[] = $last;
        }

        return $thinned;
    }

    private static function sportCode(Sport $sport): int
    {
        return match ($sport) {
            Sport::Run, Sport::TrailRun => 1,
            Sport::Road, Sport::Gravel, Sport::Mtb => 2,
            Sport::Walk => 11,
            Sport::Hike => 17,
        };
    }

    private static function fileId(int $created): string
    {
        $definition = self::definition(0, 0, [
            [0, 1, 0],
            [1, 2, 132],
            [4, 4, 134],
        ]);
        $data = chr(0).chr(6).pack('v', 255).pack('V', $created);

        return $definition.$data;
    }

    private static function courseMessage(string $name, int $sport): string
    {
        $definition = self::definition(1, 31, [
            [4, 16, 7],
            [5, 1, 0],
        ]);
        $data = chr(1).pack('a16', $name).chr($sport);

        return $definition.$data;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function lapMessage(array $points, int $started, int $seconds, int $distance): string
    {
        $definition = self::definition(2, 19, [
            [254, 2, 132],
            [253, 4, 134],
            [2, 4, 134],
            [3, 4, 133],
            [4, 4, 133],
            [5, 4, 133],
            [6, 4, 133],
            [7, 4, 134],
            [8, 4, 134],
            [9, 4, 134],
        ]);
        $first = $points[0] ?? [0.0, 0.0];
        $last = $points[array_key_last($points)] ?? $first;
        $elapsed = $seconds * 1000;
        $data = chr(2)
            .pack('v', 0)
            .pack('V', $started + $seconds)
            .pack('V', $started)
            .self::semicircle($first[1])
            .self::semicircle($first[0])
            .self::semicircle($last[1])
            .self::semicircle($last[0])
            .pack('V', $elapsed)
            .pack('V', $elapsed)
            .pack('V', $distance);

        return $definition.$data;
    }

    private static function eventMessage(int $timestamp, int $eventType): string
    {
        $definition = self::definition(3, 21, [
            [253, 4, 134],
            [0, 1, 0],
            [1, 1, 0],
        ]);

        return $definition.chr(3).pack('V', $timestamp).chr(0).chr($eventType);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function records(array $points, int $started, int $seconds, int $distance): string
    {
        $definition = self::definition(4, 20, [
            [253, 4, 134],
            [0, 4, 133],
            [1, 4, 133],
            [5, 4, 134],
        ]);
        $bytes = $definition;
        $count = max(1, count($points) - 1);
        $travelled = 0.0;
        $previous = null;

        foreach ($points as $index => $point) {
            if ($previous !== null) {
                $travelled += self::metres($previous, $point);
            }

            $previous = $point;
            $along = (int) round($travelled * 100);
            $timestamp = $started + (int) round($seconds * ($index / $count));
            $bytes .= chr(4)
                .pack('V', $timestamp)
                .self::semicircle($point[1])
                .self::semicircle($point[0])
                .pack('V', min($distance, $along));
        }

        return $bytes;
    }

    private static function header(int $dataSize): string
    {
        $prefix = chr(14).chr(16).pack('v', 2132).pack('V', $dataSize).'.FIT';

        return $prefix.self::crc16($prefix);
    }

    /**
     * @param  list<array{0: int, 1: int, 2: int}>  $fields
     */
    private static function definition(int $local, int $global, array $fields): string
    {
        $bytes = chr(0x40 | $local).chr(0).chr(0).pack('v', $global).chr(count($fields));

        foreach ($fields as $field) {
            $bytes .= chr($field[0]).chr($field[1]).chr($field[2]);
        }

        return $bytes;
    }

    private static function semicircle(float $degrees): string
    {
        $value = (int) round($degrees * (2 ** 31) / 180);

        return pack('V', $value & 0xFFFFFFFF);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function distanceCentimetres(array $points): int
    {
        $metres = 0.0;
        $previous = null;

        foreach ($points as $point) {
            if ($previous !== null) {
                $metres += self::metres($previous, $point);
            }

            $previous = $point;
        }

        return (int) round($metres * 100);
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

    private static function crc16(string $bytes): string
    {
        $table = [
            0x0000, 0xCC01, 0xD801, 0x1400, 0xF001, 0x3C00, 0x2800, 0xE401,
            0xA001, 0x6C00, 0x7800, 0xB401, 0x5000, 0x9C01, 0x8801, 0x4400,
        ];
        $crc = 0;
        $length = strlen($bytes);

        for ($index = 0; $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            $tmp = $table[$crc & 0xF];
            $crc = ($crc >> 4) & 0x0FFF;
            $crc = $crc ^ $tmp ^ $table[$byte & 0xF];
            $tmp = $table[$crc & 0xF];
            $crc = ($crc >> 4) & 0x0FFF;
            $crc = $crc ^ $tmp ^ $table[($byte >> 4) & 0xF];
        }

        return pack('v', $crc & 0xFFFF);
    }
}
