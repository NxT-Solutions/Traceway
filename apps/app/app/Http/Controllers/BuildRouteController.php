<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RoutingEngine;
use App\Http\Requests\BuildRouteRequest;
use App\Http\Responses\BuiltRoute;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class BuildRouteController extends Controller
{
    #[Post(
        path: '/routes/build',
        operationId: 'buildRoute',
        summary: 'Join points along the roads',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/BuildRouteRequest',
        description: 'The points to join, and how the roads should be chosen.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The line through those points.',
        content: new JsonContent(ref: '#/components/schemas/BuiltRoute'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The points are not enough, or the roads miss one.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'Those points did not join along the roads.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(BuildRouteRequest $buildRouteRequest, RoutingEngine $routingEngine): JsonResponse
    {
        $waypoints = $buildRouteRequest->waypoints();

        if (count($waypoints) < 2) {
            return response()->json([
                'message' => 'Add a point on the map, then draw the route.',
            ], 422);
        }

        $finish = $buildRouteRequest->boolean('finish');
        $start = $waypoints[0];
        $last = array_last($waypoints);

        if (! $buildRouteRequest->boolean('follow')) {
            $closing = $finish && $last !== $start ? [$last, $start] : [];
            $line = $closing === [] ? $waypoints : array_merge($waypoints, [$start]);

            return response()->json(BuiltRoute::from($line, $waypoints, $finish ? $closing : null, $waypoints)->toArray());
        }

        $ridden = $waypoints;
        $closing = null;

        try {
            $through = $routingEngine->trace($buildRouteRequest->toQuery(), $waypoints);
            $trimmed = RouteCleanliness::withoutSpurs($through->coordinates);
            $onThePoint = 25.0;
            $userLine = RouteCleanliness::keepingVisits($trimmed, $through->coordinates, $waypoints, $onThePoint);
            $ridden = count($userLine) >= 2 ? $userLine : $through->coordinates;

            if (! RouteCleanliness::visits($ridden, $waypoints, $onThePoint)) {
                return response()->json([
                    'message' => 'The roads between those points do not reach each one. Move a point onto a road the ride can use.',
                ], 422);
            }

            if ($finish && $last !== $start) {
                $closing = $routingEngine->closeLoop($buildRouteRequest->toQuery(), $ridden, $last, $start);
            }
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Those points did not join along the roads. Try a point closer to a road.',
            ], 502);
        }

        $user = $ridden;
        $tail = $closing instanceof GeneratedRoute ? $closing->coordinates : [];
        $line = $tail === [] ? $user : array_merge($user, array_slice($tail, 1));

        return response()->json(BuiltRoute::from($line, $user, $finish ? $tail : null, $waypoints, $through->ascentMetres)->toArray());
    }
}
