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

    public static function embed(string $id): string
    {
        return URL::signedRoute('routes.share.embed', ['savedRoute' => $id]);
    }

    public static function gpx(string $id): string
    {
        return URL::signedRoute('routes.share.gpx', ['savedRoute' => $id]);
    }

    public static function fit(string $id): string
    {
        return URL::signedRoute('routes.share.fit', ['savedRoute' => $id]);
    }

    /**
     * The picture a chat shows for the link. The version changes when the
     * picture is drawn again, so a chat that kept an old one fetches anew.
     */
    public static function image(string $id, ?int $version): string
    {
        return URL::signedRoute('routes.share.image', ['savedRoute' => $id, 'v' => $version ?? 0]);
    }

    public static function fitLeg(string $id, int $position): string
    {
        return URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $id, 'leg' => $position]);
    }

    public static function gpxLeg(string $id, int $position): string
    {
        return URL::signedRoute('routes.share.gpx.leg', ['savedRoute' => $id, 'leg' => $position]);
    }
}
