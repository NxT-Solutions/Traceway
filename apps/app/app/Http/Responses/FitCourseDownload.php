<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Courses\DeviceName;
use App\Domain\Courses\FitCourse;
use App\Domain\Routing\Sport;
use App\Queries\Routes\SharedCourse;
use App\Routes\SavedLeg;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A saved route, or one land leg of it, as a FIT file. Share links and the device app send the same bytes.
 */
final class FitCourseDownload
{
    public static function course(SharedCourse $sharedCourse): Response
    {
        $name = self::name($sharedCourse->name);

        return self::response(FitCourse::encode($name, $sharedCourse->sport, $sharedCourse->coordinates), $name);
    }

    public static function leg(SharedCourse $sharedCourse, int $leg): Response
    {
        $savedLeg = $sharedCourse->legs[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        $name = DeviceName::forLeg(self::name($sharedCourse->name), $leg, $savedLeg->sport)->value;

        return self::response(FitCourse::encode($name, $savedLeg->sport, $savedLeg->coordinates), $name);
    }

    public static function name(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'Traceway' : $name;
    }

    public static function file(string $name, string $extension): string
    {
        $slug = Str::slug($name);

        return ($slug === '' ? 'traceway' : $slug).'.'.$extension;
    }

    private static function response(string $bytes, string $name): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.self::file($name, 'fit').'"',
        ]);
    }
}
