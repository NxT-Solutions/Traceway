<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\Distance;
use App\Domain\Routing\Sport;
use InvalidArgumentException;

/**
 * A Garmin course file. The watch or bike computer follows the positions on
 * its own map. With route details, each position carries its height, so the
 * device can show the climbs ahead, and the turns and stops are course
 * points the device warns about before the rider reaches them.
 */
final class FitCourse
{
    private const int EPOCH = 631_065_600;

    /** Most watches keep about this many course points. More are dropped on the device. */
    public const int MAX_COURSE_POINTS = 200;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function encode(string $name, Sport $sport, array $coordinates, ?RouteDetails $routeDetails = null): string
    {
        if (count($coordinates) < 2) {
            throw new InvalidArgumentException('A course needs at least two points.');
        }

        $deviceName = DeviceName::forCourse($name);
        $points = self::thin($coordinates);
        $started = time() - self::EPOCH;
        $distance = self::distanceCentimetres($points);
        $seconds = max(1, (int) round(($distance / 100) / 5.5));

        // Details are measured along the full line. The thinned line is a
        // little shorter, so their distances shrink to match it.
        $scale = $routeDetails instanceof RouteDetails ? ($distance / 100) / max(1.0, Distance::along($coordinates)) : 1.0;

        $data = self::fileId($started)
            .self::courseMessage($deviceName->value, self::sportCode($sport))
            .self::lapMessage($points, $started, $seconds, $distance, $routeDetails)
            .self::eventMessage($started, 0)
            .self::records($points, $started, $seconds, $distance, $routeDetails, $scale)
            .self::eventMessage($started + $seconds, 4)
            .self::coursePoints($routeDetails, $started, $seconds, $distance, $scale);

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

        if ($last !== null && ($thinned === [] || array_last($thinned) !== $last)) {
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
        $data = chr(1).pack('a16', mb_strcut($name, 0, 15, 'UTF-8')).self::byte($sport);

        return $definition.$data;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function lapMessage(array $points, int $started, int $seconds, int $distance, ?RouteDetails $routeDetails): string
    {
        $fields = [
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
        ];

        if ($routeDetails instanceof RouteDetails) {
            $fields[] = [21, 2, 132];
            $fields[] = [22, 2, 132];
        }

        $definition = self::definition(2, 19, $fields);
        $first = $points[0] ?? [0.0, 0.0];
        $last = array_last($points) ?? $first;
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

        if ($routeDetails instanceof RouteDetails) {
            $data .= pack('v', min(0xFFFE, $routeDetails->ascentMetres)).pack('v', min(0xFFFE, $routeDetails->descentMetres));
        }

        return $definition.$data;
    }

    private static function eventMessage(int $timestamp, int $eventType): string
    {
        $definition = self::definition(3, 21, [
            [253, 4, 134],
            [0, 1, 0],
            [1, 1, 0],
        ]);

        return $definition.chr(3).pack('V', $timestamp).chr(0).self::byte($eventType);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function records(array $points, int $started, int $seconds, int $distance, ?RouteDetails $routeDetails, float $scale): string
    {
        $heights = $routeDetails instanceof RouteDetails && $routeDetails->profile !== [];
        $fields = [
            [253, 4, 134],
            [0, 4, 133],
            [1, 4, 133],
            [5, 4, 134],
        ];

        if ($heights) {
            $fields[] = [2, 2, 132];
        }

        $definition = self::definition(4, 20, $fields);
        $bytes = $definition;
        $count = max(1, count($points) - 1);
        $travelled = 0.0;
        $previous = null;

        foreach ($points as $index => $point) {
            if ($previous !== null) {
                $travelled += Distance::metres($previous, $point);
            }

            $previous = $point;
            $along = (int) round($travelled * 100);
            $timestamp = $started + (int) round($seconds * ($index / $count));
            $bytes .= chr(4)
                .pack('V', $timestamp)
                .self::semicircle($point[1])
                .self::semicircle($point[0])
                .pack('V', min($distance, $along));

            if ($heights) {
                $bytes .= self::altitude($routeDetails->elevationAt($travelled / max(0.0001, $scale)));
            }
        }

        return $bytes;
    }

    /**
     * Height as a FIT altitude: metres above 500 m below sea level, in fifths.
     */
    private static function altitude(?float $metres): string
    {
        if ($metres === null) {
            return pack('v', 0xFFFF);
        }

        return pack('v', max(0, min(0xFFFE, (int) round(($metres + 500) * 5))));
    }

    /**
     * The turns and the stops, in order along the course. A long route with
     * more than a watch keeps drops slight bends first, then the latest turns,
     * and keeps every stop.
     */
    private static function coursePoints(?RouteDetails $routeDetails, int $started, int $seconds, int $distance, float $scale): string
    {
        if (! $routeDetails instanceof RouteDetails || ($routeDetails->cues === [] && $routeDetails->stops === [])) {
            return '';
        }

        $points = [];

        foreach ($routeDetails->stops as $stop) {
            $points[] = [
                'metres' => $stop->distanceMetres,
                'longitude' => $stop->longitude,
                'latitude' => $stop->latitude,
                'type' => self::stopCode($stop),
                'name' => $stop->name ?? $stop->kind->label(),
                'rank' => 0,
            ];
        }

        foreach ($routeDetails->cues as $cue) {
            $points[] = [
                'metres' => $cue->distanceMetres,
                'longitude' => $cue->longitude,
                'latitude' => $cue->latitude,
                'type' => self::turnCode($cue->turn),
                'name' => self::cueName($cue),
                'rank' => in_array($cue->turn, [Turn::SlightLeft, Turn::SlightRight, Turn::Straight], strict: true) ? 2 : 1,
            ];
        }

        if (count($points) > self::MAX_COURSE_POINTS) {
            usort($points, fn (array $a, array $b): int => [$a['rank'], $a['metres']] <=> [$b['rank'], $b['metres']]);
            $points = array_slice($points, 0, self::MAX_COURSE_POINTS);
        }

        usort($points, fn (array $a, array $b): int => $a['metres'] <=> $b['metres']);

        $bytes = self::definition(5, 32, [
            [254, 2, 132],
            [1, 4, 134],
            [2, 4, 133],
            [3, 4, 133],
            [4, 4, 134],
            [5, 1, 0],
            [6, 16, 7],
        ]);

        foreach ($points as $index => $point) {
            $along = min($distance, (int) round($point['metres'] * $scale * 100));
            $timestamp = $started + (int) round($seconds * ($distance > 0 ? $along / $distance : 0.0));
            $bytes .= chr(5)
                .pack('v', $index)
                .pack('V', $timestamp)
                .self::semicircle($point['latitude'])
                .self::semicircle($point['longitude'])
                .pack('V', $along)
                .self::byte($point['type'])
                .pack('a16', mb_strcut($point['name'], 0, 15, 'UTF-8'));
        }

        return $bytes;
    }

    private static function cueName(Cue $cue): string
    {
        return $cue->street ?? $cue->turn->label();
    }

    private static function turnCode(Turn $turn): int
    {
        return match ($turn) {
            Turn::Left => 6,
            Turn::Right => 7,
            Turn::Straight => 8,
            Turn::SlightLeft => 19,
            Turn::SharpLeft => 20,
            Turn::SlightRight => 21,
            Turn::SharpRight => 22,
            Turn::UTurn => 23,
        };
    }

    private static function stopCode(Stop $stop): int
    {
        return match ($stop->kind) {
            StopKind::Water => 3,
            StopKind::Cafe => 4,
            StopKind::Toilet => 39,
        };
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
        $bytes = self::byte(0x40 | $local).chr(0).chr(0).pack('v', $global).self::byte(count($fields));

        foreach ($fields as $field) {
            $bytes .= self::byte($field[0]).self::byte($field[1]).self::byte($field[2]);
        }

        return $bytes;
    }

    private static function byte(int $value): string
    {
        return chr($value & 0xFF);
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
        return (int) round(Distance::along($points) * 100);
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
