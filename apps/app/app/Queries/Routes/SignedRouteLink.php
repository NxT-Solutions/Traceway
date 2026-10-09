<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use Illuminate\Support\Facades\URL;

/**
 * Signed share links from a route id. Callers do not need the Eloquent model.
 */
final class SignedRouteLink
{
    public static function share(string $id): string
    {
        return URL::signedRoute('routes.share', ['savedRoute' => $id]);
    }

    public static function gpx(string $id): string
    {
        return URL::signedRoute('routes.share.gpx', ['savedRoute' => $id]);
    }

    public static function fit(string $id): string
    {
        return URL::signedRoute('routes.share.fit', ['savedRoute' => $id]);
    }
}
