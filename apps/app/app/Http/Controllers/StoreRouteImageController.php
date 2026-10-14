<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\StoreRouteImageCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Http\Requests\StoreRouteImageRequest;
use App\Models\User;
use App\Routes\RouteId;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StoreRouteImageController extends Controller
{
    #[Post(
        path: '/routes/saved/{savedRoute}/image',
        operationId: 'storeRouteImage',
        description: 'The planner draws the route on the map and sends the picture, so a shared link shows it in a chat.',
        summary: 'Keep the picture of a saved route',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/StoreRouteImageRequest',
        description: 'The picture a shared link shows: the route on the map.',
        required: true,
    )]
    #[Response(response: HttpResponse::HTTP_NO_CONTENT, description: 'The picture is kept.')]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The picture is not a JPEG of 1200 by 630.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(StoreRouteImageRequest $storeRouteImageRequest, string $savedRoute, DispatchesCommands $dispatchesCommands): HttpResponse
    {
        $user = $storeRouteImageRequest->user();

        abort_unless($user instanceof User, 401);

        $dispatchesCommands->dispatch(new StoreRouteImageCommand(
            riderId: new RiderId($user->id),
            routeId: new RouteId($savedRoute),
            jpeg: $storeRouteImageRequest->jpeg(),
        ));

        return response()->noContent();
    }
}
