<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Account\RiderId;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Domain\Routing\Distance;
use App\Domain\Routing\LineDistance;
use App\Domain\Routing\Sport;
use App\Models\AvoidedStretch as AvoidedStretchModel;
use App\Routes\AvoidedStretchId;
use App\Routes\ForeignAvoidedStretch;
use App\Routes\MissingAvoidedStretch;
use App\Routes\StretchToAvoid;
use App\Routes\TooManyAvoidedStretches;
use Illuminate\Support\Facades\DB;

final readonly class EloquentAvoidedStretchRepository implements AvoidedStretchRepository
{
    /** A tap this close to a stretch already kept is the same stretch. */
    private const float SAME_METRES = 3.0;

    /** A point this close to a kept stretch of the same way is on it. */
    private const float ON_THE_WAY_METRES = 5.0;

    /** Degrees of latitude around the point to look for the same way: a kept stretch runs up to 2 km. */
    private const float LOOK_DEGREES = 0.02;

    public function add(RiderId $riderId, StretchToAvoid $stretchToAvoid, int $most): AvoidedStretchId
    {
        return DB::transaction(function () use ($riderId, $stretchToAvoid, $most): AvoidedStretchId {
            $same = $this->sameAs($riderId, $stretchToAvoid);

            if ($same instanceof AvoidedStretchModel) {
                // Kept on foot, now on a bike: a bike plan skips it from now on too.
                if (Sport::tryFrom($same->sport)?->onFoot() === true && Sport::tryFrom($stretchToAvoid->sport)?->onFoot() === false) {
                    $same->sport = $stretchToAvoid->sport;
                    $same->surface = $stretchToAvoid->surface;
                    $same->save();
                }

                return new AvoidedStretchId($same->id);
            }

            throw_if(
                DB::table('avoided_stretches')->where('user_id', $riderId->value)->lockForUpdate()->count() >= $most,
                TooManyAvoidedStretches::limit(),
            );

            $avoidedStretch = new AvoidedStretchModel;
            $avoidedStretch->id = $avoidedStretch->newUniqueId();
            $avoidedStretch->user_id = $riderId->value;
            $avoidedStretch->latitude = $stretchToAvoid->latitude;
            $avoidedStretch->longitude = $stretchToAvoid->longitude;
            $avoidedStretch->way_id = $stretchToAvoid->wayId;
            $avoidedStretch->name = $stretchToAvoid->name;
            $avoidedStretch->sport = $stretchToAvoid->sport;
            $avoidedStretch->surface = $stretchToAvoid->surface;
            $avoidedStretch->shape = $stretchToAvoid->shape;
            $avoidedStretch->save();

            return new AvoidedStretchId($avoidedStretch->id);
        });
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
     * A kept stretch whose point is where this one's is, or one of the same
     * way that either point lies on. A way longer than 2 km is kept as the
     * part around each tap, so two taps on it overlap rather than match.
     */
    private function sameAs(RiderId $riderId, StretchToAvoid $stretchToAvoid): ?AvoidedStretchModel
    {
        $point = [$stretchToAvoid->longitude, $stretchToAvoid->latitude];
        $lookLongitude = self::LOOK_DEGREES / max(0.05, cos(deg2rad($stretchToAvoid->latitude)));
        $near = AvoidedStretchModel::query()
            ->where('user_id', $riderId->value)
            ->where('latitude', '>=', $stretchToAvoid->latitude - self::LOOK_DEGREES)
            ->where('latitude', '<=', $stretchToAvoid->latitude + self::LOOK_DEGREES)
            ->where('longitude', '>=', $stretchToAvoid->longitude - $lookLongitude)
            ->where('longitude', '<=', $stretchToAvoid->longitude + $lookLongitude)
            ->get();

        foreach ($near as $avoidedStretch) {
            $kept = [$avoidedStretch->longitude, $avoidedStretch->latitude];

            if (Distance::metres($kept, $point) <= self::SAME_METRES) {
                return $avoidedStretch;
            }

            if ($stretchToAvoid->wayId !== null && $avoidedStretch->way_id === $stretchToAvoid->wayId
                && ($this->onLine($point, $avoidedStretch->shape) || $this->onLine($kept, $stretchToAvoid->shape))) {
                return $avoidedStretch;
            }
        }

        return null;
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{0: float, 1: float}>  $line
     */
    private function onLine(array $point, array $line): bool
    {
        return count($line) >= 2 && LineDistance::of($line)->project($point)['gap'] <= self::ON_THE_WAY_METRES;
    }
}
