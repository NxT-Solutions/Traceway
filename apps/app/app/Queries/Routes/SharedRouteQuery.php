<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Sport;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

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
            layout: $rowReader->optionalString('layout'),
            legs: $this->legs($routeId, $rowReader->legs('legs')),
            details: $rowReader->details('details')?->toArray(),
        );
    }

    /**
     * @param  list<SavedLeg>  $savedLegs
     * @return list<array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, color: string|null, fit_url: string|null, details: array<string, mixed>|null}>
     */
    private function legs(RouteId $routeId, array $savedLegs): array
    {
        $legs = [];

        foreach ($savedLegs as $index => $savedLeg) {
            $legs[] = [
                ...$savedLeg->toArray(),
                'fit_url' => $savedLeg->sport instanceof Sport ? SignedRouteLink::fitLeg($routeId->value, $index + 1) : null,
                'details' => $savedLeg->details?->toArray(),
            ];
        }

        return $legs;
    }
}
