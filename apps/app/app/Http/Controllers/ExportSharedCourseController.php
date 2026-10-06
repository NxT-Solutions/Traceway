<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Domain\Routing\Sport;
use App\Models\SavedRoute;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExportSharedCourseController extends Controller
{
    public function gpx(SavedRoute $savedRoute): Response
    {
        $name = $this->name($savedRoute);

        return response(GpxCourse::encode($name, $savedRoute->coordinates), 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => 'attachment; filename="'.$this->file($name, 'gpx').'"',
        ]);
    }

    public function fit(SavedRoute $savedRoute): Response
    {
        $name = $this->name($savedRoute);

        return response(FitCourse::encode(
            $name,
            Sport::from($savedRoute->sport),
            $savedRoute->coordinates,
        ), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$this->file($name, 'fit').'"',
        ]);
    }

    private function name(SavedRoute $savedRoute): string
    {
        $name = trim($savedRoute->name);

        return $name === '' ? 'Traceway' : $name;
    }

    private function file(string $name, string $extension): string
    {
        $slug = Str::slug($name);

        return ($slug === '' ? 'traceway' : $slug).'.'.$extension;
    }
}
