<?php

declare(strict_types=1);

namespace App\Domain\Courses;

/**
 * A course file a device can follow. Garmin reads FIT. Most other bike computers and watches read GPX.
 */
enum CourseFormat: string
{
    case Fit = 'fit';
    case Gpx = 'gpx';

    public function mediaType(): string
    {
        return match ($this) {
            self::Fit => 'application/octet-stream',
            self::Gpx => 'application/gpx+xml',
        };
    }
}
