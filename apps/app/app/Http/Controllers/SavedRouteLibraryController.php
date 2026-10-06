<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedRouteLibraryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return Inertia::render('saved', [
            'routes' => $user->savedRoutes()->latest()->get()->map(fn (SavedRoute $route): array => [
                'id' => $route->id,
                'name' => $route->name,
                'sport' => $route->sport,
                'surface' => $route->surface,
                'elevation' => $route->elevation,
                'roads' => $route->roads,
                'distance_metres' => $route->distance_metres,
                'ascent_metres' => $route->ascent_metres,
                'url' => $route->shareUrl(),
                'created_at' => $route->created_at?->toIso8601String(),
                'coordinates' => $route->coordinates,
            ])->all(),
        ]);
    }
}
