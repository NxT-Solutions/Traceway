<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use InvalidArgumentException;

/**
 * A GPX track a watch or GPS can follow.
 */
final class GpxCourse
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function encode(string $name, array $coordinates): string
    {
        if (count($coordinates) < 2) {
            throw new InvalidArgumentException('A course needs at least two points.');
        }

        $label = htmlspecialchars(
            trim($name) === '' ? 'Traceway' : trim($name),
            ENT_XML1 | ENT_QUOTES,
            'UTF-8',
        );
        $points = '';

        foreach ($coordinates as [$longitude, $latitude]) {
            $points .= '<trkpt lat="'.$latitude.'" lon="'.$longitude.'"></trkpt>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<gpx version="1.1" creator="Traceway"><trk><name>'.$label.'</name><trkseg>'
            .$points
            .'</trkseg></trk></gpx>';
    }

    /**
     * One track per leg of a multisport plan. A leg with a single point, such
     * as a swim pin, is a waypoint instead.
     *
     * @param  list<array{name: string, type: string, coordinates: list<array{0: float, 1: float}>}>  $legs
     */
    public static function encodeLegs(string $name, array $legs): string
    {
        if ($legs === []) {
            throw new InvalidArgumentException('A multisport course needs a leg.');
        }

        $waypoints = '';
        $tracks = '';

        foreach ($legs as $leg) {
            $label = self::text($leg['name']);
            $type = self::text($leg['type']);

            if (count($leg['coordinates']) < 2) {
                foreach ($leg['coordinates'] as [$longitude, $latitude]) {
                    $waypoints .= '<wpt lat="'.$latitude.'" lon="'.$longitude.'"><name>'.$label.'</name><type>'.$type.'</type></wpt>';
                }

                continue;
            }

            $points = '';

            foreach ($leg['coordinates'] as [$longitude, $latitude]) {
                $points .= '<trkpt lat="'.$latitude.'" lon="'.$longitude.'"></trkpt>';
            }

            $tracks .= '<trk><name>'.$label.'</name><type>'.$type.'</type><trkseg>'.$points.'</trkseg></trk>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<gpx version="1.1" creator="Traceway"><metadata><name>'.self::text(trim($name) === '' ? 'Traceway' : trim($name)).'</name></metadata>'
            .$waypoints
            .$tracks
            .'</gpx>';
    }

    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
