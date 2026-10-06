<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SavedRoute;
use Inertia\Inertia;
use Inertia\Response;

class ShowSharedRouteController extends Controller
{
    public function __invoke(SavedRoute $savedRoute): Response
    {
        return Inertia::render('share', [
            'route' => [
                'name' => $savedRoute->name,
                'sport' => $savedRoute->sport,
                'surface' => $savedRoute->surface,
                'elevation' => $savedRoute->elevation,
                'roads' => $savedRoute->roads,
                'distance_metres' => $savedRoute->distance_metres,
                'ascent_metres' => $savedRoute->ascent_metres,
                'coordinates' => $savedRoute->coordinates,
                'gpx_url' => $savedRoute->gpxUrl(),
                'fit_url' => $savedRoute->fitUrl(),
            ],
        ]);
    }
}
