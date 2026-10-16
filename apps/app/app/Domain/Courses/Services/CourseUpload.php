<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use App\Domain\Routing\Sport;

/**
 * One course on its way to a service: the full title for its library, the short name the device shows,
 * and both files, so each service takes the format it reads.
 */
final readonly class CourseUpload
{
    public function __construct(
        public string $title,
        public string $deviceName,
        public Sport $sport,
        public string $fit,
        public string $gpx,
        public int $distanceMetres,
        public int $ascentMetres,
        public float $startLatitude,
        public float $startLongitude,
        public string $externalId,
        public string $updatedAt,
    ) {}
}
