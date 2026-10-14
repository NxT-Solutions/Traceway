<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Routes\SharedRouteQuery;
use App\Routes\RouteId;
use Inertia\Inertia;
use Inertia\Response;

class ShowSharedRouteEmbedController extends Controller
{
    /**
     * Only the map of a shared route, for the card a chat app shows after a
     * plan. It runs on Traceway, so it is always the current map.
     */
    public function __invoke(string $savedRoute, SharedRouteQuery $sharedRouteQuery): Response
    {
        return Inertia::render('share', [
            ...$sharedRouteQuery->page(new RouteId($savedRoute))->toArray(),
            'embed' => true,
        ]);
    }
}
