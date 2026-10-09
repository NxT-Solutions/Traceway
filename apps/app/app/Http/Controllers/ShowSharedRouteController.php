<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Routes\SharedRouteQuery;
use App\Routes\RouteId;
use Inertia\Inertia;
use Inertia\Response;

class ShowSharedRouteController extends Controller
{
    public function __invoke(string $savedRoute, SharedRouteQuery $sharedRouteQuery): Response
    {
        return Inertia::render('share', [
            'route' => $sharedRouteQuery->find(new RouteId($savedRoute))->toArray(),
        ]);
    }
}
