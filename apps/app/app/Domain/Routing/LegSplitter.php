<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * Cuts a line at the points it was planned through, in their order, so each
 * stretch between two points can be measured or planned again on its own.
 *
 * A loop can pass near a point twice. Each point is matched on the line
 * after the one before it, at the first close pass, not the closest one
 * anywhere.
 */
final class LegSplitter
{
    /** A pass this much farther than the closest still counts as the first close one. */
    private const float CLOSE_METRES = 30.0;

    /**
     * The vertex each waypoint falls on, never going back along the line.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<int>
     */
    public static function cuts(array $line, array $waypoints): array
    {
        throw_if(count($line) < 2, InvalidArgumentException::class, 'A line needs at least two points.');

        $last = count($line) - 1;
        $cuts = [];
        $from = 0;

        foreach ($waypoints as $position => $waypoint) {
            if ($position === 0) {
                $cuts[] = 0;

                continue;
            }

            if ($position === count($waypoints) - 1) {
                $cuts[] = $last;

                continue;
            }

            $distances = [];

            for ($index = $from; $index <= $last; $index++) {
                $distances[$index] = Distance::metres($waypoint, $line[$index]);
            }

            if ($distances === []) {
                $cuts[] = $last;

                continue;
            }

            $closest = min($distances);
            $cut = array_key_first($distances);

            foreach ($distances as $index => $metres) {
                $next = $distances[$index + 1] ?? INF;

                if ($metres <= $closest + self::CLOSE_METRES && $metres <= $next) {
                    $cut = $index;

                    break;
                }
            }

            $cuts[] = $cut;
            $from = $cut;
        }

        return $cuts;
    }

    /**
     * The line between each pair of waypoints in turn.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function legs(array $line, array $waypoints): array
    {
        $cuts = self::cuts($line, $waypoints);
        $legs = [];

        for ($index = 1, $count = count($cuts); $index < $count; $index++) {
            $legs[] = array_slice($line, $cuts[$index - 1], $cuts[$index] - $cuts[$index - 1] + 1);
        }

        return $legs;
    }

    /**
     * How long each leg is, in metres.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<int>
     */
    public static function metres(array $line, array $waypoints): array
    {
        if (count($line) < 2 || count($waypoints) < 2) {
            return [];
        }

        return array_map(
            fn (array $leg): int => (int) round(Distance::along($leg)),
            self::legs($line, $waypoints),
        );
    }
}
