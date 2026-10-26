<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\AvoidedStretches;
use App\Queries\JsonColumn;
use App\Queries\RowReader;
use Illuminate\Support\Facades\DB;

/**
 * The stretches a rider asked the router to skip: all of them for the map,
 * or the nearest ones for a plan.
 */
final class AvoidedStretchesQuery
{
    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * @return list<array{id: string, name: string|null, latitude: float, longitude: float, shape: list<array{0: float, 1: float}>}>
     */
    public function forRider(int $riderId): array
    {
        return array_map(
            fn (AvoidedStretch $avoidedStretch): array => [
                'id' => $avoidedStretch->id,
                'name' => $avoidedStretch->name,
                'latitude' => $avoidedStretch->latitude,
                'longitude' => $avoidedStretch->longitude,
                'shape' => $avoidedStretch->shape,
            ],
            $this->stretches($riderId),
        );
    }

    /**
     * The stretches within reach of a point, nearest first, as many as a
     * plan sends.
     *
     * @return list<AvoidedStretch>
     */
    public function near(int $riderId, float $latitude, float $longitude, float $withinMetres): array
    {
        $latitudeSpan = $withinMetres / self::METRES_PER_DEGREE;
        $longitudeSpan = $withinMetres / (self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($latitude))));

        return AvoidedStretches::nearest(
            $this->stretches($riderId, [
                $latitude - $latitudeSpan,
                $latitude + $latitudeSpan,
                $longitude - $longitudeSpan,
                $longitude + $longitudeSpan,
            ]),
            [$longitude, $latitude],
            $withinMetres,
        );
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $box  South, north, west, east.
     * @return list<AvoidedStretch>
     */
    private function stretches(int $riderId, ?array $box = null): array
    {
        $builder = DB::table('avoided_stretches')->where('user_id', $riderId);

        if ($box !== null) {
            $builder->whereBetween('latitude', [$box[0], $box[1]])->whereBetween('longitude', [$box[2], $box[3]]);
        }

        $stretches = [];

        foreach ($builder->oldest()->get(['id', 'name', 'latitude', 'longitude', 'shape']) as $row) {
            $reader = new RowReader($row);
            $stretches[] = new AvoidedStretch(
                id: $reader->string('id'),
                latitude: $reader->float('latitude'),
                longitude: $reader->float('longitude'),
                shape: JsonColumn::line($reader->mixed('shape')),
                name: $reader->optionalString('name'),
            );
        }

        return $stretches;
    }
}
