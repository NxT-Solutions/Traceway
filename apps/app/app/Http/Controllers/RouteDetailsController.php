<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\Details\RouteDetailsSource;
use App\Http\Requests\RouteDetailsRequest;
use App\Http\Responses\RouteDetailsResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RouteDetailsController extends Controller
{
    #[Post(
        path: '/routes/details',
        operationId: 'routeDetails',
        description: 'Stops come from POST /routes/stops, since they can take longer.',
        summary: 'Read the climb, surfaces, and turns of a finished line',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/RouteDetailsRequest',
        description: 'A finished line to describe.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The details of the line.',
        content: new JsonContent(ref: '#/components/schemas/RouteDetails'),
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
            $routeDetails = $routeDetailsSource->road($routeDetailsRequest->sport(), $routeDetailsRequest->coordinates());
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'The climb and the turns did not load. The route still works.',
            ], 502);
        }

        return response()->json(new RouteDetailsResponse($routeDetails)->toArray());
    }
}
