<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\RiderId;
use App\Commands\DeleteRouteCommand;
use App\Contracts\Bus\DispatchesCommands;
use App\Models\User;
use App\Routes\RouteId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes\Delete;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DestroySavedRouteController extends Controller
{
    #[Delete(
        path: '/routes/saved/{savedRoute}',
        operationId: 'deleteSavedRoute',
        description: 'A route owned by someone else is rejected.',
        summary: "Delete one of the signed-in person's routes",
        security: [['session' => []]],
        tags: ['Library'],
    )]
    #[Parameter(
        name: 'savedRoute',
        description: 'The saved route.',
        in: 'path',
        required: true,
        schema: new Schema(type: 'string', format: 'uuid'),
    )]
    #[Response(response: HttpResponse::HTTP_FOUND, description: 'The route is gone.')]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_FORBIDDEN,
        description: 'The route belongs to someone else.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The route does not exist.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(Request $request, string $savedRoute, DispatchesCommands $dispatchesCommands): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $dispatchesCommands->dispatch(new DeleteRouteCommand(
            routeId: new RouteId($savedRoute),
            riderId: new RiderId($user->id),
        ));

        return to_route('routes.library');
    }
}
