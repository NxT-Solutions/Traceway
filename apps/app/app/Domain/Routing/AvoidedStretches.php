<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Picks which of a rider's avoided stretches a plan sends to the router, and
 * trims a long way down to the part around the tap.
 */
final class AvoidedStretches
{
    /**
     * Valhalla takes 50 excluded points by default. A plan sends fewer, so a
     * server set a little lower still answers.
     */
    public const int MOST_PER_PLAN = 40;

    /** A way longer than this is cut to the part either side of the tap. */
    public const float LONGEST_METRES = 2_000.0;

    /** Half the length of the band laid across an avoided road, along it. */
    public const float BAND_HALF_LENGTH_METRES = 8.0;

    /**
     * Half its width: both carriageways of the road, and a cycle path or
     * pavement mapped as its own way beside it.
     */
    public const float BAND_HALF_WIDTH_METRES = 10.0;

    /** The band stays this far from either end, so the roads that meet there stay open. */
    public const float BAND_END_GAP_METRES = 4.0;

    private const float METRES_PER_DEGREE = 111_320.0;

    /**
     * A small closed ring laid across the middle of a stretch. Valhalla
     * leaves out every way it touches, in both directions: a point only
     * leaves out the way going one direction, so a ride the other way still
     * took the road.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function band(AvoidedStretch $avoidedStretch): array
    {
        $lineDistance = LineDistance::of($avoidedStretch->shape);
        $total = $lineDistance->totalMetres();
        $at = $lineDistance->project($avoidedStretch->point())['metres'];
        $low = min(self::BAND_END_GAP_METRES, $total / 2);
        $high = max($total - self::BAND_END_GAP_METRES, $total / 2);
        $from = max($low, min($at, $high) - self::BAND_HALF_LENGTH_METRES);
        $to = min($high, max($at, $low) + self::BAND_HALF_LENGTH_METRES);

        // A stretch too short to keep clear of its ends gets a band a metre long.
        if ($to - $from < 1.0) {
            $middle = ($from + $to) / 2;
            $from = max(0.0, $middle - 0.5);
            $to = min($total, $middle + 0.5);
        }

        $start = $lineDistance->pointAt($from);
        $end = $lineDistance->pointAt($to);
        $lonScale = self::METRES_PER_DEGREE * max(0.01, cos(deg2rad($avoidedStretch->latitude)));
        $dx = ($end[0] - $start[0]) * $lonScale;
        $dy = ($end[1] - $start[1]) * self::METRES_PER_DEGREE;
        $length = max(0.001, hypot($dx, $dy));
        $offsetLon = (-$dy / $length) * self::BAND_HALF_WIDTH_METRES / $lonScale;
        $offsetLat = ($dx / $length) * self::BAND_HALF_WIDTH_METRES / self::METRES_PER_DEGREE;

        return [
            [$start[0] + $offsetLon, $start[1] + $offsetLat],
            [$end[0] + $offsetLon, $end[1] + $offsetLat],
            [$end[0] - $offsetLon, $end[1] - $offsetLat],
            [$start[0] - $offsetLon, $start[1] - $offsetLat],
            [$start[0] + $offsetLon, $start[1] + $offsetLat],
        ];
    }

    /**
     * The stretches a plan for the sport can be sent.
     *
     * @param  list<AvoidedStretch>  $avoid
     * @return list<AvoidedStretch>
     */
    public static function forSport(array $avoid, Sport $sport): array
    {
        return array_values(array_filter($avoid, fn (AvoidedStretch $avoidedStretch): bool => $avoidedStretch->concerns($sport)));
    }

    /**
     * A point half way along a line: inside the way, away from the junction
     * at either end, where Valhalla would close every way that meets.
     *
     * @param  list<array{0: float, 1: float}>  $shape
     * @return array{0: float, 1: float}
     */
    public static function middle(array $shape): array
    {
        $lineDistance = LineDistance::of($shape);

        return $lineDistance->pointAt($lineDistance->totalMetres() / 2);
    }

    /**
     * The stretches within reach of a point, nearest first.
     *
     * @param  list<AvoidedStretch>  $avoid
     * @param  array{0: float, 1: float}  $point
     * @return list<AvoidedStretch>
     */
    public static function nearest(array $avoid, array $point, float $withinMetres, int $most = self::MOST_PER_PLAN): array
    {
        $near = [];

        foreach ($avoid as $avoidedStretch) {
            $metres = Distance::metres($point, $avoidedStretch->point());

            if ($metres <= $withinMetres) {
                $near[] = ['metres' => $metres, 'stretch' => $avoidedStretch];
            }
        }

        usort($near, fn (array $left, array $right): int => $left['metres'] <=> $right['metres']);

        return array_slice(array_column($near, 'stretch'), 0, max(0, $most));
    }

    /**
     * A way of more than 2 km cut to 1 km either side of the point.
     *
     * @param  list<array{0: float, 1: float}>  $shape
     * @param  array{0: float, 1: float}  $point
     * @return list<array{0: float, 1: float}>
     */
    public static function clipped(array $shape, array $point): array
    {
        if (count($shape) < 2 || Distance::along($shape) <= self::LONGEST_METRES) {
            return $shape;
        }

        $lineDistance = LineDistance::of($shape);
        $at = $lineDistance->project($point)['metres'];
        $half = self::LONGEST_METRES / 2;
        $from = max(0.0, $at - $half);
        $to = min($lineDistance->totalMetres(), $at + $half);
        $clipped = [$lineDistance->pointAt($from)];

        foreach ($shape as $index => $vertex) {
            $along = $lineDistance->cumulative[$index] ?? 0.0;

            if ($along > $from && $along < $to) {
                $clipped[] = $vertex;
            }
        }

        $clipped[] = $lineDistance->pointAt($to);

        return $clipped;
    }
}
