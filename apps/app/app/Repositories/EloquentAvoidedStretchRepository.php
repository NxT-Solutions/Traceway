<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\Distance;
use App\Domain\Routing\RoadEdge;
use App\Models\AvoidedStretch as AvoidedStretchModel;
use App\Routes\AvoidedStretchId;
use App\Routes\ForeignAvoidedStretch;
use App\Routes\MissingAvoidedStretch;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAvoidedStretchRepository implements AvoidedStretchRepository
{
    /** A tap this close to a stretch already kept is the same stretch. */
    private const float SAME_METRES = 3.0;

    public function add(RiderId $riderId, RoadEdge $roadEdge): AvoidedStretch
    {
        return DB::transaction(function () use ($riderId, $roadEdge): AvoidedStretch {
            $same = $this->sameAs($riderId, $roadEdge);

            if ($same instanceof AvoidedStretchModel) {
                return self::toDomain($same);
            }

            $avoidedStretch = new AvoidedStretchModel;
            $avoidedStretch->id = $avoidedStretch->newUniqueId();
            $avoidedStretch->user_id = $riderId->value;
            $avoidedStretch->latitude = $roadEdge->latitude;
            $avoidedStretch->longitude = $roadEdge->longitude;
            $avoidedStretch->way_id = $roadEdge->wayId;
            $avoidedStretch->name = $roadEdge->name;
            $avoidedStretch->shape = $roadEdge->shape;
            $avoidedStretch->save();

            return self::toDomain($avoidedStretch);
        });
    }

    public function countFor(RiderId $riderId): int
    {
        return DB::table('avoided_stretches')->where('user_id', $riderId->value)->count();
    }

    public function deleteOwned(AvoidedStretchId $avoidedStretchId, RiderId $riderId): void
    {
        DB::transaction(function () use ($avoidedStretchId, $riderId): void {
            $row = DB::table('avoided_stretches')->where('id', $avoidedStretchId->value)->lockForUpdate()->first(['id', 'user_id']);

            if ($row === null) {
                throw MissingAvoidedStretch::id($avoidedStretchId->value);
            }

            $owner = $row->user_id ?? null;

            if ((! is_int($owner) && (! is_string($owner) || ! is_numeric($owner))) || (int) $owner !== $riderId->value) {
                throw ForeignAvoidedStretch::id($avoidedStretchId->value);
            }

            DB::table('avoided_stretches')->where('id', $avoidedStretchId->value)->delete();
        });
    }

    /**
     * A stretch of the same way with the same ends, or one whose point is
     * where this tap landed.
     */
    private function sameAs(RiderId $riderId, RoadEdge $roadEdge): ?AvoidedStretchModel
    {
        $first = $roadEdge->shape[0] ?? null;
        $last = $roadEdge->shape[count($roadEdge->shape) - 1] ?? null;
        $near = AvoidedStretchModel::query()
            ->where('user_id', $riderId->value)
            ->where('latitude', '>=', $roadEdge->latitude - 0.02)
            ->where('latitude', '<=', $roadEdge->latitude + 0.02)
            ->where('longitude', '>=', $roadEdge->longitude - 0.03)
            ->where('longitude', '<=', $roadEdge->longitude + 0.03)
            ->get();

        foreach ($near as $avoidedStretch) {
            if (Distance::metres([$avoidedStretch->longitude, $avoidedStretch->latitude], [$roadEdge->longitude, $roadEdge->latitude]) <= self::SAME_METRES) {
                return $avoidedStretch;
            }

            $shape = $avoidedStretch->shape;
            $ends = [$shape[0] ?? null, $shape[count($shape) - 1] ?? null];

            if ($roadEdge->wayId !== null && $avoidedStretch->way_id === $roadEdge->wayId
                && $first !== null && $last !== null && $ends[0] !== null && $ends[1] !== null
                && (self::samePoint($ends[0], $first) && self::samePoint($ends[1], $last)
                    || self::samePoint($ends[0], $last) && self::samePoint($ends[1], $first))) {
                return $avoidedStretch;
            }
        }

        return null;
    }

    /**
     * @param  array{0: float, 1: float}  $left
     * @param  array{0: float, 1: float}  $right
     */
    private static function samePoint(array $left, array $right): bool
    {
        return Distance::metres($left, $right) <= 1.0;
    }

    private static function toDomain(AvoidedStretchModel $avoidedStretchModel): AvoidedStretch
    {
        return new AvoidedStretch(
            id: $avoidedStretchModel->id,
            latitude: $avoidedStretchModel->latitude,
            longitude: $avoidedStretchModel->longitude,
            shape: $avoidedStretchModel->shape,
            name: $avoidedStretchModel->name,
        );
    }
}
