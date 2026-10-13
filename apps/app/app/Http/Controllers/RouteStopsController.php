<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Http\Requests\RouteDetailsRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RouteStopsController extends Controller
{
    #[Post(
        path: '/routes/stops',
        operationId: 'routeStops',
        summary: 'Find water, toilets, and cafés along a finished line',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/RouteDetailsRequest',
        description: 'A finished line to search along.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The stops, in order along the line.',
        content: new JsonContent(
            required: ['stops'],
            properties: [new Property(property: 'stops', type: 'array', items: new Items(ref: '#/components/schemas/RouteStop'))],
        ),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The line is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'The map server did not answer.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(RouteDetailsRequest $routeDetailsRequest, RouteDetailsSource $routeDetailsSource): JsonResponse
    {
        try {
            $stops = $routeDetailsSource->stops($routeDetailsRequest->sport(), $routeDetailsRequest->coordinates());
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Water and cafés did not load. The route still works.',
            ], 502);
        }

        return response()->json([
            'stops' => new RouteDetails([], 0, 0, [], [], $stops)->toArray()['stops'],
        ]);
    }
}
