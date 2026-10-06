<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedRouteRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StoreSavedRouteController extends Controller
{
    public function __invoke(StoreSavedRouteRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $route = $user->savedRoutes()->create($request->validated());

        return response()->json([
            'id' => $route->id,
            'url' => $route->shareUrl(),
        ]);
    }
}
