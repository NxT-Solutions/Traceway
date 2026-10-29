<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Whether a loop looks like one to a rider. Riding back down a road is
 * RouteCleanliness's to judge; this judges the outline: a figure of eight,
 * a loop pinched in the middle, one that comes back past the pin halfway
 * round, or one that runs for long beside a road it already rode, such as a
 * narrow finger out and back on two parallel roads.
 *
 * The limits come from 180 loops drawn round ten Belgian pins, on the road
 * and on foot: every loop past one of them looked wrong on the map.
 */
final class LoopShape
{
    /**
     * 4πA/P², the area the loop goes round against its length. A circle is 1,
     * a square 0.79 and a 3:1 oblong 0.59. The two halves of a figure of
     * eight go round opposite ways and cancel, so it falls towards 0.
     */
    public const float ROUNDNESS = 0.3;

    /** From a fifth to four fifths of the way round, a loop stays this far from the pin. */
    public const float PIN_METRES = 300.0;

    /** Closer than this to a part of itself well further on, a loop rides beside itself. */
    public const float BESIDE_METRES = 120.0;

    /** At most this share of a loop may ride beside itself, such as two roads into one village. */
    public const float BESIDE_SHARE = 0.06;

    /** Parts of a loop this far apart along it are different parts, not one road. */
    private const float APART_METRES = 1_500.0;

    /** Near the pin, the way out and the way home may run side by side. */
    private const float HOME_METRES = 800.0;

    private const float STEP_METRES = 20.0;

    private const float EARTH_RADIUS_METRES = 6_371_000.0;

    /**
     * Only a line that comes back to where it started is judged: a piece of
     * a route, such as a closing road, is not a loop.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}  $pin
     */
    public static function misshapen(array $line, array $pin): bool
    {
        $points = self::projected($line, $pin);
        $first = $points[0] ?? null;
        $last = $points[count($points) - 1] ?? null;

        if (count($points) < 4 || $first === null || $last === null || self::distance($first, $last) > self::PIN_METRES) {
            return false;
        }

        [$steps, $along] = self::resampled($points);
        $length = $along[count($along) - 1] ?? 0.0;

        if ($length <= 0.0) {
            return false;
        }

        return self::roundness($points, $length) < self::ROUNDNESS
            || self::passesPin($steps, $along, $length)
            || self::besideMetres($steps, $along, $length) > self::BESIDE_SHARE * $length;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function roundness(array $points, float $length): float
    {
        $area = 0.0;
        $count = count($points);

        foreach ($points as $index => $point) {
            $next = $points[($index + 1) % $count];
            $area += $point[0] * $next[1] - $next[0] * $point[1];
        }

        return 4 * M_PI * abs($area) / 2 / ($length * $length);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $steps
     * @param  list<float>  $along
     */
    private static function passesPin(array $steps, array $along, float $length): bool
    {
        foreach ($steps as $index => $step) {
            $at = $along[$index] ?? 0.0;

            if ($at > 0.2 * $length && $at < 0.8 * $length && hypot($step[0], $step[1]) < self::PIN_METRES) {
                return true;
            }
        }

        return false;
    }

    /**
     * The metres that run close beside a part of the loop well further on,
     * away from the pin.
     *
     * @param  list<array{0: float, 1: float}>  $steps
     * @param  list<float>  $along
     */
    private static function besideMetres(array $steps, array $along, float $length): float
    {
        $cells = [];

        foreach ($steps as $index => $step) {
            $cells[self::cell($step[0]).':'.self::cell($step[1])][] = $index;
        }

        $beside = 0.0;

        foreach ($steps as $index => $step) {
            if ($index === 0 || hypot($step[0], $step[1]) < self::HOME_METRES) {
                continue;
            }

            if (self::hasPartBeside($index, $step, $steps, $along, $cells, $length)) {
                $beside += ($along[$index] ?? 0.0) - ($along[$index - 1] ?? 0.0);
            }
        }

        return $beside;
    }

    /**
     * @param  array{0: float, 1: float}  $step
     * @param  list<array{0: float, 1: float}>  $steps
     * @param  list<float>  $along
     * @param  array<string, list<int>>  $cells
     */
    private static function hasPartBeside(int $index, array $step, array $steps, array $along, array $cells, float $length): bool
    {
        $x = self::cell($step[0]);
        $y = self::cell($step[1]);

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($cells[($x + $dx).':'.($y + $dy)] ?? [] as $other) {
                    $gap = abs(($along[$index] ?? 0.0) - ($along[$other] ?? 0.0));

                    // Round the loop either way: the end is beside the start.
                    if (min($gap, $length - $gap) > self::APART_METRES && self::distance($step, $steps[$other]) < self::BESIDE_METRES) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The line in metres east and north of the pin.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  array{0: float, 1: float}  $pin
     * @return list<array{0: float, 1: float}>
     */
    private static function projected(array $line, array $pin): array
    {
        $east = deg2rad(1.0) * self::EARTH_RADIUS_METRES * cos(deg2rad($pin[1]));
        $north = deg2rad(1.0) * self::EARTH_RADIUS_METRES;

        return array_map(
            fn (array $point): array => [($point[0] - $pin[0]) * $east, ($point[1] - $pin[1]) * $north],
            $line,
        );
    }

    /**
     * The line as points no more than STEP_METRES apart, with how far along each is.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return array{0: list<array{0: float, 1: float}>, 1: list<float>}
     */
    private static function resampled(array $points): array
    {
        $first = $points[0];
        $steps = [$first];
        $along = [0.0];
        $travelled = 0.0;

        foreach (array_slice($points, 1) as $offset => $point) {
            $previous = $points[$offset];
            $metres = self::distance($previous, $point);

            if ($metres <= 0.0) {
                continue;
            }

            $count = max(1, (int) ceil($metres / self::STEP_METRES));

            for ($part = 1; $part <= $count; $part++) {
                $share = $part / $count;
                $steps[] = [$previous[0] + ($point[0] - $previous[0]) * $share, $previous[1] + ($point[1] - $previous[1]) * $share];
                $travelled += $metres / $count;
                $along[] = $travelled;
            }
        }

        return [$steps, $along];
    }

    private static function cell(float $metres): int
    {
        return (int) floor($metres / self::BESIDE_METRES);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function distance(array $from, array $to): float
    {
        return hypot($to[0] - $from[0], $to[1] - $from[1]);
    }
}
