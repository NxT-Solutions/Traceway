<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Routes\RouteId;

interface SavedRouteRepository
{
    public function store(SaveRouteCommand $saveRouteCommand): string;

    public function deleteOwned(RouteId $routeId, RiderId $riderId): void;

    public function owns(RouteId $routeId, RiderId $riderId): bool;
}
