<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\CourseFormat;
use App\Http\Responses\CourseDownload;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\RouteId;
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
        return CourseDownload::course($sharedCourseQuery->find(new RouteId($savedRoute)), CourseFormat::Gpx);
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
        return CourseDownload::course($sharedCourseQuery->find(new RouteId($savedRoute)), CourseFormat::Fit);
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
        return CourseDownload::leg($sharedCourseQuery->find(new RouteId($savedRoute)), $leg, CourseFormat::Fit);
    }
}
