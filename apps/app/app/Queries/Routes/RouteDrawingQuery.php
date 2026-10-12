<?php

declare(strict_types=1);

namespace App\Queries\Routes;

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
     *     layout: string|null,
     *     line: list<array{0: float, 1: float}>,
     *     legs: list<array{discipline: string, distance_metres: int, line: list<array{0: float, 1: float}>}>
     * }|null
     */
    public function owned(RouteId $routeId, int $riderId): ?array
    {
        $row = DB::table('saved_routes')
            ->where('id', $routeId->value)
            ->where('user_id', $riderId)
            ->first(['id', 'name', 'sport', 'distance_metres', 'layout', 'coordinates', 'legs']);

        if ($row === null) {
            return null;
        }

        $rowReader = new RowReader($row);

        return [
            'id' => $rowReader->string('id'),
            'name' => $rowReader->string('name'),
            'sport' => $rowReader->string('sport'),
            'distance_metres' => $rowReader->int('distance_metres'),
            'layout' => $rowReader->optionalString('layout'),
            'line' => LineSimplifier::simplify($rowReader->line('coordinates'), LineSimplifier::OVERVIEW_METRES),
            'legs' => array_map(fn (SavedLeg $savedLeg): array => [
                'discipline' => $savedLeg->discipline(),
                'distance_metres' => $savedLeg->distanceMetres,
                'line' => count($savedLeg->coordinates) > 2
                    ? LineSimplifier::simplify($savedLeg->coordinates, LineSimplifier::OVERVIEW_METRES)
                    : $savedLeg->coordinates,
            ], $rowReader->legs('legs')),
        ];
    }
}
