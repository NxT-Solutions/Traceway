<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\DeviceName;
use App\Domain\Courses\FitCourse;
use App\Domain\Courses\GpxCourse;
use App\Domain\Routing\Sport;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\RouteId;
use App\Routes\SavedLeg;
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

        $gpx = $sharedCourse->legs === []
            ? GpxCourse::encode($name, $sharedCourse->coordinates)
            : GpxCourse::encodeLegs($name, array_map(
                fn (SavedLeg $savedLeg, int $index): array => [
                    'name' => $savedLeg->sport instanceof Sport ? DeviceName::forLeg($name, $index + 1, $savedLeg->sport)->value : 'Swim',
                    'type' => $savedLeg->discipline(),
                    'coordinates' => $savedLeg->coordinates,
                ],
                $sharedCourse->legs,
                array_keys($sharedCourse->legs),
            ));

        return response($gpx, 200, [
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

    #[Get(
        path: '/share/{savedRoute}/fit/{leg}',
        operationId: 'sharedFitLeg',
        summary: 'Download one leg of a shared multisport plan as FIT',
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
        name: 'leg',
        description: 'The leg, counted from 1. The swim has no course.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'integer', maximum: 5, minimum: 1),
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
        description: 'A FIT file named for the leg.',
        content: new MediaType(
            mediaType: 'application/octet-stream',
            schema: new Schema(type: 'string', format: 'binary'),
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The link is not signed.',
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'No such land leg.',
    )]
    public function fitLeg(string $savedRoute, int $leg, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        $sharedCourse = $sharedCourseQuery->find(new RouteId($savedRoute));
        $savedLeg = $sharedCourse->legs[$leg - 1] ?? null;

        abort_unless($savedLeg instanceof SavedLeg && $savedLeg->sport instanceof Sport, 404);

        $name = DeviceName::forLeg($this->name($sharedCourse->name), $leg, $savedLeg->sport)->value;

        return response(FitCourse::encode($name, $savedLeg->sport, $savedLeg->coordinates), 200, [
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
