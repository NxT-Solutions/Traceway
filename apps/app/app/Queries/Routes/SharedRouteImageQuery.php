<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Account\RiderId;
use App\Contracts\Routes\RouteImageStore;
use App\Routes\RouteId;

/**
 * The picture of a shared route, or null when its rider has not drawn it.
 */
final readonly class SharedRouteImageQuery
{
    public function __construct(private RouteImageStore $routeImageStore) {}

    public function find(RouteId $routeId): ?string
    {
        $rowReader = SavedRouteRow::find($routeId->value);

        return $this->routeImageStore->get(new RiderId($rowReader->int('user_id')), $routeId);
    }
}
