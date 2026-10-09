<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Commands\SaveRouteCommand;
use App\Contracts\Routes\SavedRouteRepository;
use App\Models\SavedRoute;
use App\Routes\ForeignRoute;
use App\Routes\MissingRoute;
use App\Routes\RouteId;
use Illuminate\Support\Facades\DB;

final readonly class EloquentSavedRouteRepository implements SavedRouteRepository
{
    public function store(SaveRouteCommand $saveRouteCommand): string
    {
        return DB::transaction(function () use ($saveRouteCommand): string {
            $savedRoute = new SavedRoute;
            $savedRoute->id = $savedRoute->newUniqueId();
            $savedRoute->user_id = $saveRouteCommand->riderId->value;
            $savedRoute->name = $saveRouteCommand->name->value;
            $savedRoute->sport = $saveRouteCommand->sport->value;
            $savedRoute->surface = $saveRouteCommand->surface->value;
            $savedRoute->elevation = $saveRouteCommand->elevation->value;
            $savedRoute->roads = $saveRouteCommand->roads;
            $savedRoute->distance_metres = $saveRouteCommand->distanceMetres;
            $savedRoute->ascent_metres = $saveRouteCommand->ascentMetres;
            $savedRoute->coordinates = $saveRouteCommand->coordinates->points;
            $savedRoute->user_coordinates = $saveRouteCommand->userCoordinates?->points;
            $savedRoute->finish_coordinates = $saveRouteCommand->finishCoordinates?->points;
            $savedRoute->save();

            return $savedRoute->id;
        });
    }

    public function deleteOwned(RouteId $routeId, RiderId $riderId): void
    {
        DB::transaction(function () use ($routeId, $riderId): void {
            $row = DB::table('saved_routes')->where('id', $routeId->value)->lockForUpdate()->first(['id', 'user_id']);

            if ($row === null) {
                throw MissingRoute::id($routeId->value);
            }

            $owner = $row->user_id ?? null;

            if ((! is_int($owner) && (! is_string($owner) || ! is_numeric($owner))) || (int) $owner !== $riderId->value) {
                throw ForeignRoute::id($routeId->value);
            }

            DB::table('saved_routes')->where('id', $routeId->value)->delete();
        });
    }
}
