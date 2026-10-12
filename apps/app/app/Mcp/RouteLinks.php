<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Queries\Routes\SignedRouteLink;

/**
 * The links an AI tool hands back: the map to look at, and the files to put
 * on a watch or bike computer.
 */
final class RouteLinks
{
    /**
     * @return array{map_url: string, garmin_url: string, gpx_url: string, library_url: string}
     */
    public static function for(string $id): array
    {
        return [
            'map_url' => SignedRouteLink::share($id),
            'garmin_url' => SignedRouteLink::fit($id),
            'gpx_url' => SignedRouteLink::gpx($id),
            'library_url' => url('/saved'),
        ];
    }

    public static function kilometres(int $metres): float
    {
        return round($metres / 1000, 1);
    }
}
