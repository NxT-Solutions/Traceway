<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Routes\RouteId;

final class SharedRouteQuery
{
    public function page(RouteId $routeId): SharedRoutePage
    {
        return new SharedRoutePage($this->find($routeId));
    }

    public function find(RouteId $routeId): SharedRoute
    {
        $rowReader = SavedRouteRow::find($routeId->value);

        return new SharedRoute(
            name: $rowReader->string('name'),
            sport: $rowReader->string('sport'),
            surface: $rowReader->string('surface'),
            elevation: $rowReader->string('elevation'),
            roads: $rowReader->string('roads'),
            distanceMetres: $rowReader->int('distance_metres'),
            ascentMetres: $rowReader->int('ascent_metres'),
            coordinates: $rowReader->line('coordinates'),
            gpxUrl: SignedRouteLink::gpx($routeId->value),
            fitUrl: SignedRouteLink::fit($routeId->value),
        );
    }
}
