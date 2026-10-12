<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Courses\CourseFormat;
use App\Domain\Courses\DeviceName;
use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Domain\Routing\Sport;
use App\Queries\Routes\SharedCourse;
use App\Routes\SavedLeg;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A saved route, or one land leg of it, as a course file. Share links and device apps send the same bytes.
 */
final class CourseDownload
{
    public static function course(SharedCourse $sharedCourse, CourseFormat $courseFormat): Response
    {
        $name = self::name($sharedCourse->name);

        $bytes = match ($courseFormat) {
            CourseFormat::Fit => FitCourse::encode($name, $sharedCourse->sport, $sharedCourse->coordinates),
            CourseFormat::Gpx => $sharedCourse->legs === []
                ? GpxCourse::encode($name, $sharedCourse->coordinates)
                : GpxCourse::encodeLegs($name, array_map(
                    fn (SavedLeg $savedLeg, int $index): array => [
                        'name' => $savedLeg->sport instanceof Sport ? DeviceName::forLeg($name, $index + 1, $savedLeg->sport)->value : 'Swim',
                        'type' => $savedLeg->discipline(),
                        'coordinates' => $savedLeg->coordinates,
                    ],
                    $sharedCourse->legs,
                    array_keys($sharedCourse->legs),
                )),
        };

        return self::response($bytes, $name, $courseFormat);
    }

    public static function leg(SharedCourse $sharedCourse, int $leg, CourseFormat $courseFormat): Response
    {
        $savedLeg = $sharedCourse->legs[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        $name = DeviceName::forLeg(self::name($sharedCourse->name), $leg, $savedLeg->sport)->value;

        $bytes = match ($courseFormat) {
            CourseFormat::Fit => FitCourse::encode($name, $savedLeg->sport, $savedLeg->coordinates),
            CourseFormat::Gpx => GpxCourse::encode($name, $savedLeg->coordinates),
        };

        return self::response($bytes, $name, $courseFormat);
    }

    private static function name(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'Traceway' : $name;
    }

    private static function response(string $bytes, string $name, CourseFormat $courseFormat): Response
    {
        $slug = Str::slug($name);

        return response($bytes, 200, [
            'Content-Type' => $courseFormat->mediaType(),
            'Content-Disposition' => 'attachment; filename="'.($slug === '' ? 'traceway' : $slug).'.'.$courseFormat->value.'"',
        ]);
    }
}
