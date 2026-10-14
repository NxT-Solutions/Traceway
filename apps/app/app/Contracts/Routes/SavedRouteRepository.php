<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

interface SavedRouteRepository
{
    public function store(SaveRouteCommand $saveRouteCommand): string;

    public function deleteOwned(RouteId $routeId, RiderId $riderId): void;

    public function owns(RouteId $routeId, RiderId $riderId): bool;

    /**
     * Keeps the details read later for a route saved without them, as the
     * details column stores them. A null climb leaves the saved one.
     *
     * @param  array<string, mixed>|null  $details
     * @param  list<SavedLeg>  $legs
     */
    public function storeDetails(RouteId $routeId, ?array $details, array $legs, ?int $ascentMetres): void;

    public function noteDetailsTried(RouteId $routeId): void;
}
