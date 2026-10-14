<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Routes\RouteId;

/**
 * The picture a shared link shows in a chat or a post: the route on the
 * map, with its name. The rider's browser draws it, since only the browser
 * has the map.
 */
interface RouteImageStore
{
    public function put(RiderId $riderId, RouteId $routeId, string $jpeg): void;

    public function get(RiderId $riderId, RouteId $routeId): ?string;

    /**
     * When the picture was last drawn, or null when there is none yet.
     */
    public function drawnAt(RiderId $riderId, RouteId $routeId): ?int;

    public function forget(RiderId $riderId, RouteId $routeId): void;

    public function forgetRider(RiderId $riderId): void;
}
