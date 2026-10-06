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
    public function __invoke(PreviewRouteRequest $request, RoutingEngine $engine): JsonResponse
    {
        try {
            $routes = $engine->candidates($request->toQuery());
        } catch (Throwable $exception) {
            report($exception);

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
            fn (GeneratedRoute $route): array => [
                'distance_metres' => $route->distanceMetres,
                'ascent_metres' => $route->ascentMetres,
                'coordinates' => $route->coordinates,
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
