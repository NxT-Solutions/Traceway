<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Bus\DispatchesCommands;
use App\Http\Requests\StoreSavedRouteRequest;
use App\Http\ValidatedRoute;
use App\Models\User;
use App\Routes\SavedRouteLink;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StoreSavedRouteController extends Controller
{
    #[Post(
        path: '/routes/saved',
        operationId: 'storeSavedRoute',
        summary: 'Save a route for the signed-in person',
        security: [['session' => []]],
        tags: ['Library'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/StoreSavedRouteRequest',
        description: 'The route the signed-in person wants to keep.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The saved route id and its signed share URL.',
        content: new JsonContent(ref: '#/components/schemas/SavedRouteLink'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The route is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(StoreSavedRouteRequest $storeSavedRouteRequest, DispatchesCommands $dispatchesCommands): JsonResponse
    {
        $user = $storeSavedRouteRequest->user();

        abort_unless($user instanceof User, 403);

        /** @var array<string, mixed> $data */
        $data = $storeSavedRouteRequest->validated();
        $link = $dispatchesCommands->dispatch(ValidatedRoute::command($user->id, $data));

        abort_unless($link instanceof SavedRouteLink, 500);

        return response()->json($link->toArray());
    }
}
