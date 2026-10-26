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
