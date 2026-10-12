<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Domain\Courses\CourseFormat;
use App\Http\Controllers\Controller;
use App\Http\Responses\CourseDownload;
use App\Models\User;
use App\Queries\Routes\SharedCourseQuery;
use App\Routes\RouteId;
use Illuminate\Http\Request;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeviceCourseController extends Controller
{
    #[Get(
        path: '/api/device/routes/{savedRoute}/{format}',
        operationId: 'deviceCourse',
        description: 'The device stores the file as a course.',
        summary: 'Download a saved route as a course file',
        security: [['deviceToken' => []]],
        tags: ['Device'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Parameter(
        name: 'format',
        description: 'fit for Garmin. gpx for a device that reads GPX.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', enum: ['fit', 'gpx']),
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A FIT or GPX file.',
        content: [
            new MediaType(mediaType: 'application/octet-stream', schema: new Schema(type: 'string', format: 'binary')),
            new MediaType(mediaType: 'application/gpx+xml', schema: new Schema(type: 'string', format: 'binary')),
        ],
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'The token is missing or the device was removed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function course(Request $request, string $savedRoute, string $format, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        return CourseDownload::course(
            $sharedCourseQuery->findOwned(new RouteId($savedRoute), $this->riderId($request)),
            CourseFormat::from($format),
        );
    }

    #[Get(
        path: '/api/device/routes/{savedRoute}/{format}/{leg}',
        operationId: 'deviceCourseLeg',
        summary: 'Download one land leg of a multisport plan as a course file',
        security: [['deviceToken' => []]],
        tags: ['Device'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Parameter(
        name: 'format',
        description: 'fit for Garmin. gpx for a device that reads GPX.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', enum: ['fit', 'gpx']),
    )]
    #[Parameter(
        name: 'leg',
        description: 'The leg, counted from 1. The swim has no course.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'integer', maximum: 5, minimum: 1),
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A FIT or GPX file named for the leg.',
        content: [
            new MediaType(mediaType: 'application/octet-stream', schema: new Schema(type: 'string', format: 'binary')),
            new MediaType(mediaType: 'application/gpx+xml', schema: new Schema(type: 'string', format: 'binary')),
        ],
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'The token is missing or the device was removed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route or land leg.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function leg(Request $request, string $savedRoute, string $format, int $leg, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        return CourseDownload::leg(
            $sharedCourseQuery->findOwned(new RouteId($savedRoute), $this->riderId($request)),
            $leg,
            CourseFormat::from($format),
        );
    }

    private function riderId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user->id;
    }
}
