<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\LineSimplifier;
use App\Queries\RowReader;
use App\Routes\RouteId;
use App\Routes\SavedLeg;
use Illuminate\Support\Facades\DB;

/**
 * A saved route as the in-chat map draws it: the overview line, and one line
 * per leg. Only the rider who saved it gets it.
 */
final class RouteDrawingQuery
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     sport: string,
     *     distance_metres: int,
     *     ascent_metres: int,
     *     layout: string|null,
     *     line: list<array{0: float, 1: float}>,
     *     legs: list<array{discipline: string, distance_metres: int, line: list<array{0: float, 1: float}>}>,
     *     stops: list<array{kind: string, longitude: float, latitude: float, name: string|null}>
     * }|null
     */
    public function owned(RouteId $routeId, int $riderId): ?array
    {
        $row = DB::table('saved_routes')
            ->where('id', $routeId->value)
            ->where('user_id', $riderId)
            ->first(['id', 'name', 'sport', 'distance_metres', 'ascent_metres', 'layout', 'coordinates', 'legs', 'details']);

        if ($row === null) {
            return null;
        }

        $rowReader = new RowReader($row);
        $legs = $rowReader->legs('legs');
        $details = $legs === []
            ? [$rowReader->details('details')]
            : array_map(fn (SavedLeg $savedLeg): ?RouteDetails => $savedLeg->details, $legs);

        return [
            'id' => $rowReader->string('id'),
            'name' => $rowReader->string('name'),
            'sport' => $rowReader->string('sport'),
            'distance_metres' => $rowReader->int('distance_metres'),
            'ascent_metres' => $rowReader->int('ascent_metres'),
            'layout' => $rowReader->optionalString('layout'),
            'line' => LineSimplifier::simplify($rowReader->line('coordinates'), LineSimplifier::OVERVIEW_METRES),
            'legs' => array_map(fn (SavedLeg $savedLeg): array => [
                'discipline' => $savedLeg->discipline(),
                'distance_metres' => $savedLeg->distanceMetres,
                'line' => count($savedLeg->coordinates) > 2
                    ? LineSimplifier::simplify($savedLeg->coordinates, LineSimplifier::OVERVIEW_METRES)
                    : $savedLeg->coordinates,
            ], $legs),
            'stops' => $this->stops($details),
        ];
    }

    /**
     * The water, toilets, and cafés on the way, from every leg.
     *
     * @param  list<RouteDetails|null>  $details
     * @return list<array{kind: string, longitude: float, latitude: float, name: string|null}>
     */
    private function stops(array $details): array
    {
        $stops = [];

        foreach ($details as $routeDetails) {
            foreach ($routeDetails->stops ?? [] as $stop) {
                $stops[] = $this->stop($stop);
            }
        }

        return $stops;
    }

    /**
     * @return array{kind: string, longitude: float, latitude: float, name: string|null}
     */
    private function stop(Stop $stop): array
    {
        return ['kind' => $stop->kind->value, 'longitude' => $stop->longitude, 'latitude' => $stop->latitude, 'name' => $stop->name];
    }
}
