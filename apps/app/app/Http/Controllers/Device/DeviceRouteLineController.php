<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Routes\DeviceRouteLineQuery;
use App\Routes\RouteId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeviceRouteLineController extends Controller
{
    #[Get(
        path: '/api/device/routes/{savedRoute}/line',
        operationId: 'deviceRouteLine',
        description: 'At most 150 points, each latitude then longitude, for a preview before the course is loaded.',
        summary: 'The shape of a saved route',
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
        description: 'The thinned line.',
        content: new JsonContent(ref: '#/components/schemas/DeviceRouteLine'),
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
    public function route(Request $request, string $savedRoute, DeviceRouteLineQuery $deviceRouteLineQuery): JsonResponse
    {
        return response()->json(['line' => $deviceRouteLineQuery->route(new RouteId($savedRoute), $this->riderId($request))]);
    }

    #[Get(
        path: '/api/device/routes/{savedRoute}/line/{leg}',
        operationId: 'deviceRouteLegLine',
        summary: 'The shape of one land leg of a multisport plan',
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
        description: 'The thinned line of the leg.',
        content: new JsonContent(ref: '#/components/schemas/DeviceRouteLine'),
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
    public function leg(Request $request, string $savedRoute, int $leg, DeviceRouteLineQuery $deviceRouteLineQuery): JsonResponse
    {
        return response()->json(['line' => $deviceRouteLineQuery->leg(new RouteId($savedRoute), $this->riderId($request), $leg)]);
    }

    private function riderId(Request $request): int
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user->id;
    }
}
