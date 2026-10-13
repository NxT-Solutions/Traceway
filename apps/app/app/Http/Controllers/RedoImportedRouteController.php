<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Courses\ImportedLine;
use App\Domain\Routing\LineFollower;
use App\Domain\Routing\RoutingEngine;
use App\Http\Requests\RedoImportedRouteRequest;
use App\Http\Responses\ImportedRoute;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Post;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class RedoImportedRouteController extends Controller
{
    /**
     * Valhalla is both the follower and the engine. As two arguments of the
     * action, Laravel would hand the one object over once and drop the
     * second, so the constructor takes them.
     */
    public function __construct(
        private readonly LineFollower $lineFollower,
        private readonly RoutingEngine $routingEngine,
    ) {}

    #[Post(
        path: '/routes/import/redo',
        operationId: 'redoImportedRoute',
        description: 'Keeps an imported line, rides it the other way, or rides it from the start pin and back. A new line follows the roads for the sport, the way the file goes, without turning back on itself.',
        summary: 'Ride an imported line again',
        security: [['session' => []]],
        tags: ['Routes'],
    )]
    #[RequestBody(
        ref: '#/components/requestBodies/RedoImportedRouteRequest',
        description: 'An imported line and how to ride it again.',
        required: true,
    )]
    #[Response(
        response: HttpResponse::HTTP_OK,
        description: 'The line to ride.',
        content: new JsonContent(ref: '#/components/schemas/ImportedRoute'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNAUTHORIZED,
        description: 'No signed-in person.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
        description: 'The line or the mode is not valid.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    #[Response(
        response: HttpResponse::HTTP_BAD_GATEWAY,
        description: 'The roads did not join up.',
        content: new JsonContent(ref: '#/components/schemas/Error'),
    )]
    public function __invoke(RedoImportedRouteRequest $redoImportedRouteRequest): JsonResponse
    {
        $line = $redoImportedRouteRequest->coordinates();
        $name = $redoImportedRouteRequest->courseName();

        if ($redoImportedRouteRequest->mode() === 'keep') {
            return response()->json(new ImportedRoute($name, $line)->toArray());
        }

        $start = $redoImportedRouteRequest->mode() === 'reverse' ? null : $redoImportedRouteRequest->start();
        $followed = $start === null ? array_reverse($line) : ImportedLine::fromNearest($line, $start);

        try {
            $generatedRoute = ImportedLine::ride($this->lineFollower, $this->routingEngine, $redoImportedRouteRequest->toQuery(), $followed, $start);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'The roads along that file did not join up for this sport. Try keeping it as it is.',
            ], 502);
        }

        return response()->json(new ImportedRoute($name, $generatedRoute->coordinates, $generatedRoute->distanceMetres)->toArray());
    }
}
