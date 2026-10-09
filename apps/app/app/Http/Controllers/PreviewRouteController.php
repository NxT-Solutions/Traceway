<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RoutingEngine;
use App\Http\Requests\PreviewRouteRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class PreviewRouteController extends Controller
{
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

        $lines = array_map(
            fn (GeneratedRoute $generatedRoute): array => [
                'distance_metres' => $generatedRoute->distanceMetres,
                'ascent_metres' => $generatedRoute->ascentMetres,
                'coordinates' => $generatedRoute->coordinates,
            ],
            $routes,
        );

        return response()->json([
            'distance_metres' => $first->distanceMetres,
            'ascent_metres' => $first->ascentMetres,
            'coordinates' => $first->coordinates,
            'routes' => $lines,
        ]);
    }
}
