<?php

declare(strict_types=1);

namespace App\Http\Controllers\Device;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Routes\DeviceRoutesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DeviceRoutesController extends Controller
{
    #[Get(
        path: '/api/device/routes',
        operationId: 'deviceRoutes',
        summary: 'The latest saved routes of the rider who linked the device',
        security: [['deviceToken' => []]],
        tags: ['Device'],
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'Up to 20 routes, newest first.',
        content: new JsonContent(
            required: ['routes'],
            properties: [
                new Property(
                    property: 'routes',
                    type: 'array',
                    items: new Items(
                        required: ['id', 'title', 'name', 'sport', 'distance_metres', 'ascent_metres', 'legs'],
                        properties: [
                            new Property(property: 'id', type: 'string', format: 'uuid'),
                            new Property(property: 'title', description: 'The route name for a menu, up to 40 characters.', type: 'string', maxLength: 40, minLength: 1),
                            new Property(property: 'name', description: 'The name the course carries on the device.', type: 'string', maxLength: 15, minLength: 1),
                            new Property(property: 'sport', type: 'string'),
                            new Property(property: 'distance_metres', type: 'integer'),
                            new Property(property: 'ascent_metres', type: 'integer'),
                            new Property(
                                property: 'legs',
                                description: 'The land legs of a multisport plan. Empty for a single sport.',
                                type: 'array',
                                items: new Items(
                                    required: ['n', 'name', 'sport'],
                                    properties: [
                                        new Property(property: 'n', description: 'The leg, counted from 1.', type: 'integer'),
                                        new Property(property: 'name', type: 'string', maxLength: 15, minLength: 1),
                                        new Property(property: 'sport', type: 'string'),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ),
            ],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'The token is missing or the device was removed.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(Request $request, DeviceRoutesQuery $deviceRoutesQuery): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return response()->json(['routes' => $deviceRoutesQuery->forRider($user->id)]);
    }
}
