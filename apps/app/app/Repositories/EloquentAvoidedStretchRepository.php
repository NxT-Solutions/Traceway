<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Domain\Routing\Distance;
use App\Models\AvoidedStretch as AvoidedStretchModel;
use App\Routes\AvoidedStretchId;
use App\Routes\ForeignAvoidedStretch;
use App\Routes\MissingAvoidedStretch;
use App\Routes\StretchToAvoid;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAvoidedStretchRepository implements AvoidedStretchRepository
{
    /** A tap this close to a stretch already kept is the same stretch. */
    private const float SAME_METRES = 3.0;

    public function add(RiderId $riderId, StretchToAvoid $stretchToAvoid): AvoidedStretchId
    {
        return DB::transaction(function () use ($riderId, $stretchToAvoid): AvoidedStretchId {
            $same = $this->sameAs($riderId, $stretchToAvoid);

            if ($same instanceof AvoidedStretchModel) {
                return new AvoidedStretchId($same->id);
            }

            $avoidedStretch = new AvoidedStretchModel;
            $avoidedStretch->id = $avoidedStretch->newUniqueId();
            $avoidedStretch->user_id = $riderId->value;
            $avoidedStretch->latitude = $stretchToAvoid->latitude;
            $avoidedStretch->longitude = $stretchToAvoid->longitude;
            $avoidedStretch->way_id = $stretchToAvoid->wayId;
            $avoidedStretch->name = $stretchToAvoid->name;
            $avoidedStretch->shape = $stretchToAvoid->shape;
            $avoidedStretch->save();

            return new AvoidedStretchId($avoidedStretch->id);
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
    private function sameAs(RiderId $riderId, StretchToAvoid $stretchToAvoid): ?AvoidedStretchModel
    {
        $first = $stretchToAvoid->shape[0] ?? null;
        $last = $stretchToAvoid->shape[count($stretchToAvoid->shape) - 1] ?? null;
        $near = AvoidedStretchModel::query()
            ->where('user_id', $riderId->value)
            ->where('latitude', '>=', $stretchToAvoid->latitude - 0.02)
            ->where('latitude', '<=', $stretchToAvoid->latitude + 0.02)
            ->where('longitude', '>=', $stretchToAvoid->longitude - 0.03)
            ->where('longitude', '<=', $stretchToAvoid->longitude + 0.03)
            ->get();

        foreach ($near as $avoidedStretch) {
            if (Distance::metres([$avoidedStretch->longitude, $avoidedStretch->latitude], [$stretchToAvoid->longitude, $stretchToAvoid->latitude]) <= self::SAME_METRES) {
                return $avoidedStretch;
            }

            $shape = $avoidedStretch->shape;
            $ends = [$shape[0] ?? null, $shape[count($shape) - 1] ?? null];

            if ($stretchToAvoid->wayId !== null && $avoidedStretch->way_id === $stretchToAvoid->wayId
                && $first !== null && $last !== null && $ends[0] !== null && $ends[1] !== null
                && ($this->samePoint($ends[0], $first) && $this->samePoint($ends[1], $last)
                    || $this->samePoint($ends[0], $last) && $this->samePoint($ends[1], $first))) {
                return $avoidedStretch;
            }
        }

        return null;
    }

    /**
     * @param  array{0: float, 1: float}  $left
     * @param  array{0: float, 1: float}  $right
     */
    private function samePoint(array $left, array $right): bool
    {
        return Distance::metres($left, $right) <= 1.0;
    }
}
