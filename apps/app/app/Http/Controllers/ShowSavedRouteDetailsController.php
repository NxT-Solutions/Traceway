<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\Details\RouteDetails;
use App\Http\Responses\RouteDetailsResponse;
use App\Models\User;
use App\Queries\Routes\SavedRouteDetailsQuery;
use App\Routes\RouteId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ShowSavedRouteDetailsController extends Controller
{
    #[Get(
        path: '/routes/saved/{savedRoute}/details',
        operationId: 'savedRouteDetails',
        summary: 'The climb, surfaces, turns, and stops kept with a saved route',
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
    #[Parameter(
        name: 'leg',
        description: 'A land leg of a multisport plan, counted from 1.',
        in: 'query',
        required: false,
        schema: new Schema(type: 'integer', maximum: 5, minimum: 1),
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The details, or null for a route saved before they were read.',
        content: new JsonContent(
            required: ['details'],
            properties: [new Property(property: 'details', ref: '#/components/schemas/RouteDetails', nullable: true)],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_NOT_FOUND,
        description: 'The rider has no such route or land leg.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(Request $request, string $savedRoute, SavedRouteDetailsQuery $savedRouteDetailsQuery): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $leg = $request->query('leg');
        $routeDetails = $savedRouteDetailsQuery->findOwned(
            new RouteId($savedRoute),
            $user->id,
            is_string($leg) && ctype_digit($leg) ? (int) $leg : null,
        );

        return response()->json([
            'details' => $routeDetails instanceof RouteDetails ? new RouteDetailsResponse($routeDetails)->toArray() : null,
        ]);
    }
}
