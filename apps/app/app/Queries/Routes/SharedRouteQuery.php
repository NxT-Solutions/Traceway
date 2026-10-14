<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Account\RiderId;
use App\Contracts\Routes\RouteImageStore;
use App\Domain\Routing\Shape;
use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\RouteId;
use App\Routes\SavedLeg;

final readonly class SharedRouteQuery
{
    public function __construct(private RouteImageStore $routeImageStore) {}

    public function page(RouteId $routeId): SharedRoutePage
    {
        return new SharedRoutePage($this->find($routeId));
    }

    public function find(RouteId $routeId): SharedRoute
    {
        $rowReader = SavedRouteRow::find($routeId->value);
        $riderId = $rowReader->int('user_id');
        $drawnAt = $this->routeImageStore->drawnAt(new RiderId($riderId), $routeId);
        $coordinates = $rowReader->line('coordinates');
        $savedLegs = $rowReader->legs('legs');

        return new SharedRoute(
            name: $rowReader->string('name'),
            sport: $rowReader->string('sport'),
            surface: $rowReader->string('surface'),
            elevation: $rowReader->string('elevation'),
            roads: $rowReader->string('roads'),
            distanceMetres: $rowReader->int('distance_metres'),
            ascentMetres: $rowReader->int('ascent_metres'),
            coordinates: $coordinates,
            gpxUrl: SignedRouteLink::gpx($routeId->value),
            fitUrl: SignedRouteLink::fit($routeId->value),
            layout: $rowReader->optionalString('layout'),
            legs: $this->legs($routeId, $savedLegs),
            details: $rowReader->details('details')?->toArray(),
            imageUrl: SignedRouteLink::image($routeId->value, $drawnAt),
            imageDrawn: $drawnAt !== null,
            riderId: $riderId,
            shape: $savedLegs === [] ? $this->shape($rowReader, $coordinates) : null,
        );
    }

    /**
     * How the route goes, so a shared link shows how it was planned.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private function shape(RowReader $rowReader, array $coordinates): string
    {
        if (($rowReader->optionalLine('user_coordinates') ?? []) !== []) {
            return 'drawn';
        }

        return Shape::of($coordinates)->value;
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
