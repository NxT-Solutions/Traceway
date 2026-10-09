<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\RouteId;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportSharedCourseController extends Controller
{
    public function gpx(string $savedRoute, SharedCourseQuery $sharedCourseQuery): Response
    {
        $sharedCourse = $sharedCourseQuery->find(new RouteId($savedRoute));
        $name = $this->name($sharedCourse->name);

        return response(GpxCourse::encode($name, $sharedCourse->coordinates), 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => 'attachment; filename="'.$this->file($name, 'gpx').'"',
        ]);
    }

    public function fit(string $savedRoute, SharedCourseQuery $sharedCourseQuery): Response
    {
        $sharedCourse = $sharedCourseQuery->find(new RouteId($savedRoute));
        $name = $this->name($sharedCourse->name);

        return response(FitCourse::encode(
            $name,
            $sharedCourse->sport,
            $sharedCourse->coordinates,
        ), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$this->file($name, 'fit').'"',
        ]);
    }

    private function name(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'Traceway' : $name;
    }

    private function file(string $name, string $extension): string
    {
        $slug = Str::slug($name);

        return ($slug === '' ? 'traceway' : $slug).'.'.$extension;
    }
}
