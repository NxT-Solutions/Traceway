<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Finds the avoided stretches a line still rides along. A router that cannot
 * skip a way by itself plans first and asks here, and every route is checked
 * once more before the rider sees it.
 *
 * Riding along means following the stretch, not crossing it: a road that
 * crosses at a junction, or one beside it, is not the stretch.
 */
final class AvoidCheck
{
    /**
     * How far from the stretch a point of the line may be and still be on it:
     * as wide as the band the router leaves out, so the other carriageway and
     * a path mapped beside the road count as the road.
     */
    private const float ON_IT_METRES = AvoidedStretches::BAND_HALF_WIDTH_METRES;

    /** The most a line may turn from the stretch and still follow it. */
    private const float SAME_WAY_DEGREES = 35.0;

    /** Metres of a stretch ridden before it counts. Short stretches count at half their length. */
    private const float RIDDEN_METRES = 30.0;

    /** About 25 m of latitude, to skip the parts of the line far from a stretch. */
    private const float MARGIN_DEGREES = 0.00025;

    private const float METRES_PER_DEGREE = 111_320.0;

    private const float SAMPLE_METRES = 5.0;

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<AvoidedStretch>  $avoid
     * @return list<string> The ids of the stretches the line rides along.
     */
    public static function crossing(array $line, array $avoid): array
    {
        if (count($line) < 2 || $avoid === []) {
            return [];
        }

        $found = [];

        foreach ($avoid as $avoidedStretch) {
            if (self::rides($line, $avoidedStretch)) {
                $found[] = $avoidedStretch->id;
            }
        }

        return $found;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    private static function rides(array $line, AvoidedStretch $avoidedStretch): bool
    {
        $shape = $avoidedStretch->shape;
        $lonScale = self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($avoidedStretch->latitude)));
        $origin = $avoidedStretch->point();
        $west = $origin[0];
        $east = $origin[0];
        $south = $origin[1];
        $north = $origin[1];
        $flat = [];

        foreach ($shape as $point) {
            $west = min($west, $point[0]);
            $east = max($east, $point[0]);
            $south = min($south, $point[1]);
            $north = max($north, $point[1]);
            $flat[] = self::flat($point, $origin, $lonScale);
        }

        // A degree of longitude is shorter than one of latitude away from the equator.
        $west -= self::MARGIN_DEGREES * 2;
        $east += self::MARGIN_DEGREES * 2;
        $south -= self::MARGIN_DEGREES;
        $north += self::MARGIN_DEGREES;
        $length = 0.0;

        for ($index = 1, $count = count($flat); $index < $count; $index++) {
            $length += hypot($flat[$index][0] - $flat[$index - 1][0], $flat[$index][1] - $flat[$index - 1][1]);
        }

        $needed = max(5.0, min(self::RIDDEN_METRES, $length / 2));
        $ridden = 0.0;

        for ($index = 1, $count = count($line); $index < $count; $index++) {
            $from = $line[$index - 1];
            $to = $line[$index];

            if (max($from[0], $to[0]) < $west || min($from[0], $to[0]) > $east
                || max($from[1], $to[1]) < $south || min($from[1], $to[1]) > $north) {
                continue;
            }

            $a = self::flat($from, $origin, $lonScale);
            $b = self::flat($to, $origin, $lonScale);
            $span = hypot($b[0] - $a[0], $b[1] - $a[1]);

            if ($span < 0.5) {
                continue;
            }

            $samples = max(1, (int) ceil($span / self::SAMPLE_METRES));
            $heading = atan2($b[1] - $a[1], $b[0] - $a[0]);

            for ($step = 0; $step < $samples; $step++) {
                $share = ($step + 0.5) / $samples;
                $sample = [$a[0] + ($b[0] - $a[0]) * $share, $a[1] + ($b[1] - $a[1]) * $share];
                $nearest = self::nearest($sample, $flat);

                // Past either end is the road the stretch meets, not the stretch.
                if ($nearest !== null && ! $nearest['beyond'] && $nearest['metres'] <= self::ON_IT_METRES && self::sameWay($heading, $nearest['heading'])) {
                    $ridden += $span / $samples;

                    if ($ridden >= $needed) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * A point in metres east and north of the origin.
     *
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $origin
     * @return array{0: float, 1: float}
     */
    private static function flat(array $point, array $origin, float $lonScale): array
    {
        return [($point[0] - $origin[0]) * $lonScale, ($point[1] - $origin[1]) * self::METRES_PER_DEGREE];
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{0: float, 1: float}>  $flat
     * @return array{metres: float, heading: float, beyond: bool}|null
     */
    private static function nearest(array $point, array $flat): ?array
    {
        $best = null;
        $last = count($flat) - 1;

        for ($index = 1, $count = count($flat); $index < $count; $index++) {
            $a = $flat[$index - 1];
            $b = $flat[$index];
            $dx = $b[0] - $a[0];
            $dy = $b[1] - $a[1];
            $lengthSquared = $dx * $dx + $dy * $dy;

            if ($lengthSquared < 0.01) {
                continue;
            }

            $along = (($point[0] - $a[0]) * $dx + ($point[1] - $a[1]) * $dy) / $lengthSquared;
            $progress = max(0.0, min(1.0, $along));
            $metres = hypot($point[0] - ($a[0] + $progress * $dx), $point[1] - ($a[1] + $progress * $dy));

            if ($best === null || $metres < $best['metres']) {
                $best = [
                    'metres' => $metres,
                    'heading' => atan2($dy, $dx),
                    'beyond' => ($index === 1 && $along < 0.0) || ($index === $last && $along > 1.0),
                ];
            }
        }

        return $best;
    }

    /** Either way along the stretch counts: a rider may meet it from either end. */
    private static function sameWay(float $heading, float $stretch): bool
    {
        $apart = fmod(abs(rad2deg($heading - $stretch)), 180.0);

        return min($apart, 180.0 - $apart) <= self::SAME_WAY_DEGREES;
    }
}
