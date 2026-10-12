<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Http\Responses\FitCourseDownload;
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
        path: '/api/device/routes/{savedRoute}/fit',
        operationId: 'deviceFit',
        description: 'The device stores the file as a course.',
        summary: 'Download a saved route as a FIT course',
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
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'A FIT file.',
        content: new MediaType(
            mediaType: 'application/octet-stream',
            schema: new Schema(type: 'string', format: 'binary'),
        ),
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
    public function fit(Request $request, string $savedRoute, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        return FitCourseDownload::course($sharedCourseQuery->findOwned(new RouteId($savedRoute), $this->riderId($request)));
    }

    #[Get(
        path: '/api/device/routes/{savedRoute}/fit/{leg}',
        operationId: 'deviceFitLeg',
        summary: 'Download one land leg of a multisport plan as a FIT course',
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
        name: 'leg',
        description: 'The leg, counted from 1. The swim has no course.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'integer', maximum: 5, minimum: 1),
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
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'The token is missing or the device was removed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route or land leg.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function fitLeg(Request $request, string $savedRoute, int $leg, SharedCourseQuery $sharedCourseQuery): HttpResponse
    {
        return FitCourseDownload::leg($sharedCourseQuery->findOwned(new RouteId($savedRoute), $this->riderId($request)), $leg);
    }

    private function riderId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user->id;
    }
}
