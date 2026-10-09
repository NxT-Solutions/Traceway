<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\RoutingEngine;
use App\Http\Requests\PreviewRouteRequest;
use App\Http\Responses\RoutePreview;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class PreviewRouteController extends Controller
{
    #[Post(
        path: '/routes/preview',
        operationId: 'previewRoute',
        summary: 'Draw candidate loops',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/PreviewRouteRequest',
        description: 'The pin, the sport, and the length.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The loops from that pin.',
        content: new JsonContent(ref: '#/components/schemas/RoutePreview'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The pin or the length is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'No loop came back.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(PreviewRouteRequest $previewRouteRequest, RoutingEngine $routingEngine): JsonResponse
    {
        try {
            $routes = $routingEngine->candidates($previewRouteRequest->toQuery());
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'No loop came back from that pin. Try another spot or a different distance.',
            ], 502);
        }

        $first = $routes[0] ?? null;

        if ($first === null) {
            return response()->json([
                'message' => 'No loop came back from that pin. Try another spot or a different distance.',
            ], 502);
        }

        return response()->json(RoutePreview::from($routes)->toArray());
    }
}
