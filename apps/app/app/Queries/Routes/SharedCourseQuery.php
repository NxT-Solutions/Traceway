<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Sport;
use App\Routes\RouteId;

final class SharedCourseQuery
{
    public function find(RouteId $routeId): SharedCourse
    {
        $rowReader = SavedRouteRow::find($routeId->value);

        return new SharedCourse(
            name: $rowReader->string('name'),
            sport: Sport::from($rowReader->string('sport')),
            coordinates: $rowReader->line('coordinates'),
        );
    }
}
