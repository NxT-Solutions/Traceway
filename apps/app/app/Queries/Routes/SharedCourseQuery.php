<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\RouteId;

final class SharedCourseQuery
{
    public function find(RouteId $routeId): SharedCourse
    {
        return $this->course(SavedRouteRow::find($routeId->value));
    }

    public function findOwned(RouteId $routeId, int $riderId): SharedCourse
    {
        return $this->course(SavedRouteRow::findOwned($routeId->value, $riderId));
    }

    private function course(RowReader $rowReader): SharedCourse
    {
        return new SharedCourse(
            name: $rowReader->string('name'),
            sport: Sport::from($rowReader->string('sport')),
            coordinates: $rowReader->line('coordinates'),
            legs: $rowReader->legs('legs'),
            details: $rowReader->details('details'),
            distanceMetres: $rowReader->int('distance_metres'),
            ascentMetres: $rowReader->int('ascent_metres'),
            updatedAt: $rowReader->iso('updated_at'),
        );
    }
}
