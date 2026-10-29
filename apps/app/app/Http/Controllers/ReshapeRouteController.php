<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\LegSplitter;
use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\Reshape;
use App\Domain\Routing\RouteCleanliness;
use App\Http\Requests\ReshapeRouteRequest;
use App\Http\Responses\BuiltRoute;
use App\Http\Responses\ReshapedLoop;
use App\Models\User;
use App\Routes\RiderAvoids;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ReshapeRouteController extends Controller
{
    #[Post(
        path: '/routes/reshape',
        operationId: 'reshapeRoute',
        description: 'Plans again only the legs the change touches and keeps the rest of the line as it was. A moved point moves only the roads around it.',
        summary: 'Change one point of a drawn route',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/ReshapeRouteRequest',
        description: 'A drawn route, the points it passes, and one change to it.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The route after the change: a loop, or a built route as Build draws it.',
        content: new JsonContent(oneOf: [
            new Schema(ref: '#/components/schemas/ReshapedLoop'),
            new Schema(ref: '#/components/schemas/BuiltRoute'),
        ]),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The change does not fit the route, or the roads miss a built point.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'The changed legs did not join along the roads, or the map server is busy.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(ReshapeRouteRequest $reshapeRouteRequest, Reshape $reshape, RiderAvoids $riderAvoids): JsonResponse
    {
        $user = $reshapeRouteRequest->user();

        abort_unless($user instanceof User, 403);

        $waypoints = $reshapeRouteRequest->waypoints();
        $loop = $reshapeRouteRequest->isLoop();
        $finish = ! $loop && $reshapeRouteRequest->boolean('finish');

        try {
            $routeChange = $reshapeRouteRequest->change();
            // The avoided roads around the route, and around where it goes now.
            $reach = $routeChange->point === null ? $waypoints : [...$waypoints, $routeChange->point];
            $routeQuery = $riderAvoids->apply($reshapeRouteRequest->toQuery(), $user->id, $reach);
            $reshaped = $reshape->apply($routeQuery, $reshapeRouteRequest->line(), $waypoints, $routeChange, $finish, $loop);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return response()->json(['message' => $invalidArgumentException->getMessage()], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (MapServerBusy) {
            return response()->json([
                'message' => 'The map server is busy. Try again in a minute.',
            ], HttpResponse::HTTP_BAD_GATEWAY);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'The roads around that point did not join up. Try a spot closer to a road.',
            ], HttpResponse::HTTP_BAD_GATEWAY);
        }

        if ($loop) {
            return response()->json(ReshapedLoop::from($reshaped, $routeQuery)->toArray());
        }

        // A built route keeps every point on its own street, and its way home apart.
        $points = $finish ? array_slice($reshaped->waypoints, 0, -1) : $reshaped->waypoints;
        $cuts = LegSplitter::cuts($reshaped->coordinates, $reshaped->waypoints);
        $userEnd = $finish ? $cuts[count($cuts) - 2] : count($reshaped->coordinates) - 1;
        $user = array_slice($reshaped->coordinates, 0, $userEnd + 1);

        if (! RouteCleanliness::visits($user, $points, 25.0)) {
            return response()->json([
                'message' => 'The roads between those points do not reach each one. Move a point onto a road the ride can use.',
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $finishLine = $finish ? array_slice($reshaped->coordinates, $userEnd) : null;

        return response()->json(BuiltRoute::from($reshaped->coordinates, $user, $finishLine, $points, 0, $routeQuery->avoid)->toArray());
    }
}
