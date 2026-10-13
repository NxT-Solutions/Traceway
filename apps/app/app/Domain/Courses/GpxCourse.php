<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Distance;
use InvalidArgumentException;

/**
 * A GPX track a watch or GPS can follow. With route details, each point
 * carries its height and each stop is a waypoint.
 */
final class GpxCourse
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function encode(string $name, array $coordinates, ?RouteDetails $routeDetails = null): string
    {
        if (count($coordinates) < 2) {
            throw new InvalidArgumentException('A course needs at least two points.');
        }

        $label = self::text(trim($name) === '' ? 'Traceway' : trim($name));

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<gpx version="1.1" creator="Traceway">'
            .self::stops($routeDetails)
            .'<trk><name>'.$label.'</name><trkseg>'
            .self::points($coordinates, $routeDetails)
            .'</trkseg></trk></gpx>';
    }

    /**
     * One track per leg of a multisport plan. A leg with a single point, such
     * as a swim pin, is a waypoint instead.
     *
     * @param  list<array{name: string, type: string, coordinates: list<array{0: float, 1: float}>, details?: RouteDetails|null}>  $legs
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

            $routeDetails = $leg['details'] ?? null;
            $waypoints .= self::stops($routeDetails);
            $tracks .= '<trk><name>'.$label.'</name><type>'.$type.'</type><trkseg>'.self::points($leg['coordinates'], $routeDetails).'</trkseg></trk>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<gpx version="1.1" creator="Traceway"><metadata><name>'.self::text(trim($name) === '' ? 'Traceway' : trim($name)).'</name></metadata>'
            .$waypoints
            .$tracks
            .'</gpx>';
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private static function points(array $coordinates, ?RouteDetails $routeDetails): string
    {
        $heights = $routeDetails instanceof RouteDetails && $routeDetails->profile !== [];
        $points = '';
        $travelled = 0.0;
        $previous = null;

        foreach ($coordinates as $coordinate) {
            if ($previous !== null) {
                $travelled += Distance::metres($previous, $coordinate);
            }

            $previous = $coordinate;
            $elevation = $heights ? $routeDetails->elevationAt($travelled) : null;
            $points .= '<trkpt lat="'.$coordinate[1].'" lon="'.$coordinate[0].'">'
                .($elevation === null ? '' : '<ele>'.round($elevation, 1).'</ele>')
                .'</trkpt>';
        }

        return $points;
    }

    private static function stops(?RouteDetails $routeDetails): string
    {
        if (! $routeDetails instanceof RouteDetails) {
            return '';
        }

        $waypoints = '';

        foreach ($routeDetails->stops as $stop) {
            $waypoints .= '<wpt lat="'.$stop->latitude.'" lon="'.$stop->longitude.'">'
                .'<name>'.self::text($stop->name ?? $stop->kind->label()).'</name>'
                .'<sym>'.self::symbol($stop->kind).'</sym>'
                .'<type>'.$stop->kind->value.'</type>'
                .'</wpt>';
        }

        return $waypoints;
    }

    /**
     * The Garmin waypoint symbol, which most other devices read too.
     */
    private static function symbol(StopKind $stopKind): string
    {
        return match ($stopKind) {
            StopKind::Water => 'Drinking Water',
            StopKind::Toilet => 'Restroom',
            StopKind::Cafe => 'Restaurant',
        };
    }

    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
