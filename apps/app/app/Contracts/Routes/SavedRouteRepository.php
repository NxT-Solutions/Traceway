<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Domain\Routing\Details\RouteDetails;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

interface SavedRouteRepository
{
    public function store(SaveRouteCommand $saveRouteCommand): string;

    public function deleteOwned(RouteId $routeId, RiderId $riderId): void;

    public function owns(RouteId $routeId, RiderId $riderId): bool;

    /**
     * Keeps the details read later for a route saved without them. A null
     * climb leaves the saved one as it is.
     *
     * @param  list<SavedLeg>  $legs
     */
    public function storeDetails(RouteId $routeId, ?RouteDetails $routeDetails, array $legs, ?int $ascentMetres): void;

    public function noteDetailsTried(RouteId $routeId): void;
}
