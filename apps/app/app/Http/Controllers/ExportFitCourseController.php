<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\FitCourse;
use App\Http\Requests\ExportFitCourseRequest;
use Symfony\Component\HttpFoundation\Response;

class ExportFitCourseController extends Controller
{
    public function __invoke(ExportFitCourseRequest $request): Response
    {
        $bytes = FitCourse::encode(
            $request->courseName(),
            $request->sport(),
            $request->coordinates(),
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="traceway.fit"',
        ]);
    }
}
