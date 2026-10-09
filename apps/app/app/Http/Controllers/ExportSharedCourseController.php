<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\RouteId;
use Illuminate\Support\Str;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ExportSharedCourseController extends Controller
{
    #[Get(
        path: '/share/{savedRoute}/gpx',
        operationId: 'sharedGpx',
        summary: 'Download a shared course as GPX',
        security: [],
        tags: ['Share'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Parameter(
        name: 'signature',
        description: 'The signature on the share link.',
        in: 'query',
        required: true,
        schema: new Schema(type: 'string'),
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A GPX file.',
        content: new MediaType(
            mediaType: 'application/gpx+xml',
            schema: new Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The link is not signed.',
    )]
    public function gpx(string $savedRoute, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        $sharedCourse = $sharedCourseQuery->find(new RouteId($savedRoute));
        $name = $this->name($sharedCourse->name);

        return response(GpxCourse::encode($name, $sharedCourse->coordinates), 200, [
            'Content-Type' => 'application/gpx+xml',
            'Content-Disposition' => 'attachment; filename="'.$this->file($name, 'gpx').'"',
        ]);
    }

    #[Get(
        path: '/share/{savedRoute}/fit',
        operationId: 'sharedFit',
        summary: 'Download a shared course as FIT',
        security: [],
        tags: ['Share'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Parameter(
        name: 'signature',
        description: 'The signature on the share link.',
        in: 'query',
        required: true,
        schema: new Schema(type: 'string'),
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A FIT file.',
        content: new MediaType(
            mediaType: 'application/octet-stream',
            schema: new Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The link is not signed.',
    )]
    public function fit(string $savedRoute, SharedCourseQuery $sharedCourseQuery): HttpResponse
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
