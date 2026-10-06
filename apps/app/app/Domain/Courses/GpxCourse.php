<?php

declare(strict_types=1);

namespace App\Domain\Courses;

use InvalidArgumentException;

/**
 * A GPX track a bike computer can follow.
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
}
