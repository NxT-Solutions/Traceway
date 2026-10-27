<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Measures how much of a line travels back over a road it already used.
 * A clean loop scores near zero. An out-and-back scores high.
 */
final class RouteCleanliness
{
    /**
     * The street a loop starts on may be ridden both ways: it is the way out
     * and the way home. Doubling back this close to the start is allowed.
     */
    public const float START_STREET_METRES = 500.0;

    /**
     * The finish may ride home along the road the loop left on, for this far
     * and for at most HOME_SHARE of the line. Farther, the same road both
     * ways is a turn-back, not the way home.
     */
    public const float HOME_METRES = 1_500.0;

    public const float HOME_SHARE = 0.15;

    /**
     * Past this many metres doubled back away from the start street, or
     * DOUBLED_SHARE of a short line, a line turns back on a road. A long loop
     * gets no more room than this, however long it is.
     */
    public const float DOUBLED_METRES = 1_000.0;

    public const float DOUBLED_SHARE = 0.12;

    /**
     * How far off the length a short loop may come out and still be on it.
     * A longer loop gets less: see lengthTolerance().
     */
    public const float LENGTH_SHARE = 0.15;

    /**
     * A longer loop may be off by this many metres times the square root of
     * its kilometres: 2.2 km on 20 km, 3.9 km on 60 km, 5 km on 100 km, and
     * 7.1 km on 200 km. Up to 11 km, LENGTH_SHARE is the smaller.
     */
    public const float LENGTH_METRES_PER_ROOT_KM = 500.0;

    /**
     * When the rider asked for flat or hilly, each metre a kilometre that a
     * loop climbs more than the flattest (or less than the hilliest) weighs
     * like being 1/CLIMB_PER_KM_WEIGHT further off the length. At most it
     * weighs like being a whole tolerance off, so a flatter loop never wins
     * by coming out further off the length than a loop may be.
     */
    public const float CLIMB_PER_KM_WEIGHT = 50.0;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function reverseShare(array $coordinates): float
    {
        ['reversed' => $reversed, 'total' => $total] = self::reversal($coordinates);

        return $total === 0.0 ? 0.0 : $reversed / $total;
    }

    /**
     * How many metres ride back over a road already used, leaving out the
     * start street and the ride home along it. Unlike the share, this does
     * not grow with the loop: kilometres back down one road always count.
     *
     * Roads in $before count as already used, and only this line's metres
     * are counted, so a leg of a multisport route is judged against the
     * legs before it. Where $before starts, the transition, is a start
     * street too: a chain leg may come home down it.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  array{0: float, 1: float}|null  $start  Where the loop starts, when not the line's first point.
     * @param  list<array{0: float, 1: float}>  $before  The line ridden before this one.
     */
    public static function doubledMetres(array $coordinates, ?array $start = null, array $before = []): float
    {
        return self::reversal($coordinates, $start ?? ($coordinates[0] ?? null), $before)['away'];
    }

    /**
     * The share and the doubled metres from one pass over the line.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  array{0: float, 1: float}|null  $start
     * @return array{share: float, metres: float}
     */
    public static function doubling(array $coordinates, ?array $start = null): array
    {
        ['reversed' => $reversed, 'away' => $away, 'total' => $total] = self::reversal($coordinates, $start ?? ($coordinates[0] ?? null));

        return ['share' => $total === 0.0 ? 0.0 : $reversed / $total, 'metres' => $away];
    }

    /** The most metres a line this long may ride back down a road away from the start street. */
    public static function doubledLimit(float $lineMetres): float
    {
        return min(self::DOUBLED_METRES, $lineMetres * self::DOUBLED_SHARE);
    }

    /**
     * What doubled metres under the limit cost, against being off the asked
     * length. Each weighs twice its share of the line, so on a short run 5%
     * doubled costs as much as being 10% off. Past 40 km a doubled kilometre
     * stays at 5%, so a long line is not let off.
     */
    public static function doubledCost(float $doubled, float $lineMetres): float
    {
        return 2 * $doubled / max(1.0, min($lineMetres, 40_000.0));
    }

    /**
     * The metres that ride back over a road already used, all of them and
     * those away from the start street, and the metres of the whole line.
     * The roads in $before are matched against but not counted.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  array{0: float, 1: float}|null  $start
     * @param  list<array{0: float, 1: float}>  $before
     * @return array{reversed: float, away: float, total: float}
     */
    private static function reversal(array $coordinates, ?array $start = null, array $before = []): array
    {
        /** @var list<array{from: array{0: float, 1: float}, to: array{0: float, 1: float}, fromLat: int, fromLon: int, toLat: int, toLon: int, bearing: float}> $used */
        $used = [];
        // Earlier segments by the grid cells they pass near, so each segment
        // is checked only against the ones close enough to match. Checking
        // every earlier segment took seconds on a 180 km loop.
        /** @var array<string, list<int>> $nearby */
        $nearby = [];
        $reversedMetres = 0.0;
        $totalMetres = 0.0;
        // Each segment by its metres, whether it rides back, and how much of
        // it is off the start street. The ride home is found from the end afterwards.
        /** @var list<array{metres: float, reversed: bool, outside: float}> $segments */
        $segments = [];
        $transition = $before[0] ?? null;
        $streets = $start === null ? [] : ($transition === null ? [$start] : [$start, $transition]);

        foreach ([[$before, false], [$coordinates, true]] as [$line, $counted]) {
            $from = null;

            foreach ($line as $coordinate) {
                if ($from === null) {
                    $from = $coordinate;

                    continue;
                }

                $metres = self::metres($from, $coordinate);

                // A point a few metres on is measured from the last one kept, so
                // a road drawn with close points still counts in full.
                if ($metres < 8.0) {
                    continue;
                }

                $bearing = self::bearing($from, $coordinate);
                $fromLat = self::cell($from[1], 0.00035);
                $fromLon = self::cell($from[0], 0.00045);
                $toLat = self::cell($coordinate[1], 0.00035);
                $toLon = self::cell($coordinate[0], 0.00045);

                $midpoint = [($from[0] + $coordinate[0]) / 2, ($from[1] + $coordinate[1]) / 2];

                if ($counted) {
                    $totalMetres += $metres;
                    $close = [];

                    foreach ([$from, $coordinate, $midpoint] as $point) {
                        foreach ($nearby[self::gridKey($point)] ?? [] as $edgeIndex) {
                            $close[$edgeIndex] = true;
                        }
                    }

                    $candidates = [];

                    foreach (array_keys($close) as $edgeIndex) {
                        $edge = $used[$edgeIndex] ?? null;

                        if ($edge !== null) {
                            $candidates[] = $edge;
                        }
                    }

                    $reversed = self::reverses($candidates, $from, $coordinate, $fromLat, $fromLon, $toLat, $toLon, $bearing);

                    if ($reversed) {
                        $reversedMetres += $metres;
                    }

                    $segments[] = [
                        'metres' => $metres,
                        'reversed' => $reversed,
                        'outside' => $reversed && $streets !== [] ? self::offStartStreet($from, $coordinate, $metres, $streets) : $metres,
                    ];
                }

                foreach (self::gridKeysNear($from, $coordinate) as $key) {
                    $nearby[$key][] = count($used);
                }

                $used[] = [
                    'from' => $from,
                    'to' => $coordinate,
                    'fromLat' => $fromLat,
                    'fromLon' => $fromLon,
                    'toLat' => $toLat,
                    'toLon' => $toLon,
                    'bearing' => $bearing,
                ];
                $from = $coordinate;
            }
        }

        if ($start === null) {
            return ['reversed' => $reversedMetres, 'away' => $reversedMetres, 'total' => $totalMetres];
        }

        // Only a line that comes back to where it started, or to the
        // transition, has a ride home.
        $first = $coordinates[0] ?? null;
        $last = array_last($coordinates);
        $home = $last !== null && array_any(
            $first === null ? $streets : [...$streets, $first],
            fn (array $street): bool => self::metres($street, $last) <= self::START_STREET_METRES,
        );

        return ['reversed' => $reversedMetres, 'away' => self::away($segments, $home ? $totalMetres : 0.0), 'total' => $totalMetres];
    }

    /**
     * The doubled-back metres off the start street. The last run of doubled
     * road before the finish is the ride home along the way out, so it is
     * left out too, up to HOME_METRES. A short gap in that run is a junction.
     * The run can end part way along a segment, so one long segment counts
     * the same as the road drawn with many points.
     *
     * @param  list<array{metres: float, reversed: bool, outside: float}>  $segments
     * @param  float  $homeFrom  The metres of a line that comes home; 0 for one that ends elsewhere.
     */
    private static function away(array $segments, float $homeFrom): float
    {
        $longestHome = min(self::HOME_METRES, $homeFrom * self::HOME_SHARE);
        $homeRun = 0.0;
        $gap = 0.0;
        $past = 0.0;
        $index = count($segments) - 1;

        for (; $index >= 0; $index--) {
            $segment = $segments[$index];

            if (! $segment['reversed']) {
                $gap += $segment['metres'];

                if ($gap > 60.0) {
                    break;
                }

                continue;
            }

            $room = $longestHome - $homeRun;

            if ($segment['metres'] > $room) {
                $past = $segment['outside'] * (($segment['metres'] - $room) / $segment['metres']);
                $index--;

                break;
            }

            $homeRun += $segment['metres'];
            $gap = 0.0;
        }

        $away = $past;

        foreach (array_slice($segments, 0, max(0, $index + 1)) as $segment) {
            if ($segment['reversed']) {
                $away += $segment['outside'];
            }
        }

        return $away;
    }

    /**
     * The metres of a segment farther than the start street from each start,
     * measured in short pieces so a long segment from the pin is not counted
     * all in or all out.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @param  non-empty-list<array{0: float, 1: float}>  $streets
     */
    private static function offStartStreet(array $from, array $to, float $metres, array $streets): float
    {
        $pieces = max(1, (int) ceil($metres / 25.0));
        $outside = 0.0;

        for ($piece = 0; $piece < $pieces; $piece++) {
            $along = ($piece + 0.5) / $pieces;
            $point = [$from[0] + (($to[0] - $from[0]) * $along), $from[1] + (($to[1] - $from[1]) * $along)];

            if (! array_any($streets, fn (array $street): bool => self::metres($street, $point) <= self::START_STREET_METRES)) {
                $outside += $metres / $pieces;
            }
        }

        return $outside;
    }

    /**
     * A line that comes back this close to a point it passed has closed a knot.
     */
    public const float KNOT_METRES = 25.0;

    /**
     * A knot is cut when it is at most this long, and at most KNOT_SHARE of
     * the line: a small loop hung off the route, not a lobe of a figure of
     * eight a rider would want.
     */
    public const float KNOT_MOST_METRES = 3_000.0;

    public const float KNOT_SHARE = 0.15;

    /**
     * Drops a stretch that leaves a point and comes straight back along the same road.
     * A real corner stays. An out-and-back spur, like the one east of Bergom, does not.
     * A small knot goes first, so the road out to it and back is a spur too.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    public static function withoutSpurs(array $coordinates): array
    {
        $original = $coordinates;
        $coordinates = self::withoutKnots($coordinates);

        // The kept line, how far along it each point is, and the bearing of
        // each of its segments. The look-back in cutThinReturn reads the last
        // two instead of measuring every segment again for every new point.
        $stack = [];
        /** @var list<float> $along */
        $along = [];
        /** @var list<float> $bearings */
        $bearings = [];

        $push = static function (array $point) use (&$stack, &$along, &$bearings): void {
            /** @var array{0: float, 1: float} $point */
            $tip = $stack === [] ? null : $stack[count($stack) - 1];

            if ($tip === null) {
                $along[] = 0.0;
            } else {
                $along[] = ($along[count($along) - 1] ?? 0.0) + self::metres($tip, $point);
                $bearings[] = self::bearing($tip, $point);
            }

            $stack[] = $point;
        };

        foreach ($coordinates as $coordinate) {
            if ($stack !== []) {
                $tip = array_last($stack);

                if (self::metres($tip, $coordinate) < 8.0) {
                    continue;
                }
            }

            while (count($stack) >= 2) {
                $last = count($stack) - 1;
                $tip = $stack[$last];
                $previous = $stack[$last - 1];

                $turn = abs(($bearings[$last - 1] ?? self::bearing($previous, $tip)) - self::bearing($tip, $coordinate));
                $turn = min($turn, 360.0 - $turn);

                if ($turn < 150.0 || self::distanceToSegment($coordinate, $previous, $tip) > 40.0) {
                    break;
                }

                array_pop($stack);
                array_pop($along);
                array_pop($bearings);
            }

            $cut = self::cutThinReturn($stack, $along, $bearings, $coordinate);

            if ($cut !== null) {
                $kept = array_slice($stack, 0, $cut['keep']);

                if ($cut['join'] !== null) {
                    $kept[] = $cut['join'];
                }

                if ($kept !== $stack) {
                    $along = array_slice($along, 0, $cut['keep']);
                    $bearings = array_slice($bearings, 0, $cut['keep'] - 1);
                    $stack = array_slice($stack, 0, $cut['keep']);

                    if ($cut['join'] !== null) {
                        $push($cut['join']);
                    }

                    continue;
                }
            }

            if ($stack === []) {
                $push($coordinate);

                continue;
            }

            $tip = array_last($stack);

            if (self::metres($tip, $coordinate) >= 8.0) {
                $push($coordinate);
            }
        }

        $stack = self::peelClosingRetrace($stack);
        $stack = self::closeToStart($coordinates, $stack);

        if (count($stack) < 2 || count($stack) < (int) (count($original) * 0.2)) {
            return $original;
        }

        return $stack;
    }

    /**
     * Cuts the small loops a router ties to turn round at a point: the line
     * rides out, circles a block, and comes back to where it left. The knot
     * goes, and the line carries on from where it closed.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    public static function withoutKnots(array $coordinates): array
    {
        $most = min(self::KNOT_MOST_METRES, self::KNOT_SHARE * Distance::along($coordinates));
        /** @var list<array{0: float, 1: float}> $kept */
        $kept = [];
        /** @var list<float> $along */
        $along = [];
        /** @var array<string, list<int>> $cells */
        $cells = [];

        foreach ($coordinates as $coordinate) {
            $here = $kept === [] ? 0.0 : ($along[count($along) - 1] ?? 0.0) + self::metres($kept[count($kept) - 1], $coordinate);
            $closed = null;

            foreach (self::knotCellsNear($coordinate) as $key) {
                foreach ($cells[$key] ?? [] as $index) {
                    // An index past the end was cut with an earlier knot. One
                    // since reused is checked against the point it is now.
                    $point = $kept[$index] ?? null;

                    if ($point === null) {
                        continue;
                    }

                    $round = $here - ($along[$index] ?? 0.0);

                    // The earliest point it comes back to closes the whole knot.
                    if ($round > 4 * self::KNOT_METRES && $round <= $most && self::metres($point, $coordinate) <= self::KNOT_METRES) {
                        $closed = $closed === null ? $index : min($closed, $index);
                    }
                }
            }

            if ($closed !== null) {
                $kept = array_slice($kept, 0, $closed + 1);
                $along = array_slice($along, 0, $closed + 1);

                continue;
            }

            $kept[] = $coordinate;
            $along[] = $here;
            $cells[self::knotCell($coordinate)][] = count($kept) - 1;
        }

        return count($kept) < 2 ? $coordinates : $kept;
    }

    /**
     * @param  array{0: float, 1: float}  $point
     */
    private static function knotCell(array $point): string
    {
        return self::cell($point[1], 0.0003).':'.self::cell($point[0], 0.0005);
    }

    /**
     * The cell a point is in and the eight around it, so a point just over a
     * cell's edge is still found.
     *
     * @param  array{0: float, 1: float}  $point
     * @return list<string>
     */
    private static function knotCellsNear(array $point): array
    {
        $lat = self::cell($point[1], 0.0003);
        $lon = self::cell($point[0], 0.0005);
        $keys = [];

        foreach ([-1, 0, 1] as $latStep) {
            foreach ([-1, 0, 1] as $lonStep) {
                $keys[] = ($lat + $latStep).':'.($lon + $lonStep);
            }
        }

        return $keys;
    }

    /**
     * Prefer the cleaned line, unless that cut a selected point off the ride.
     *
     * @param  list<array{0: float, 1: float}>  $candidate
     * @param  list<array{0: float, 1: float}>  $original
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<array{0: float, 1: float}>
     */
    public static function keepingVisits(array $candidate, array $original, array $waypoints, float $tolerance = 80.0): array
    {
        if (self::visits($candidate, $waypoints, $tolerance) || ! self::visits($original, $waypoints, $tolerance)) {
            return $candidate;
        }

        return $original;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public static function visits(array $line, array $waypoints, float $tolerance = 80.0): bool
    {
        if (count($line) < 2 || $waypoints === []) {
            return false;
        }

        foreach ($waypoints as $waypoint) {
            $near = false;

            for ($index = 1, $count = count($line); $index < $count; $index++) {
                $from = $line[$index - 1] ?? null;
                $to = $line[$index] ?? null;

                if ($from === null || $to === null) {
                    continue;
                }

                if (self::distanceToSegment($waypoint, $from, $to) <= $tolerance) {
                    $near = true;

                    break;
                }
            }

            if (! $near) {
                return false;
            }
        }

        return true;
    }

    /**
     * Removes a there-and-back along one road, including a hook at the far end.
     * The return can sit beside the way out, so it is matched to the road itself,
     * not only to a vertex. A wide circuit is left alone.
     *
     * Returns how many points to keep and where to rejoin, or null to keep
     * the line as it is.
     *
     * @param  list<array{0: float, 1: float}>  $stack
     * @param  list<float>  $along  Metres from the first point to each point.
     * @param  list<float>  $bearings  The bearing of each segment.
     * @param  array{0: float, 1: float}  $next
     * @return array{keep: int, join: array{0: float, 1: float}|null}|null
     */
    private static function cutThinReturn(array $stack, array $along, array $bearings, array $next): ?array
    {
        $count = count($stack);

        if ($count < 4) {
            return null;
        }

        $tip = $stack[$count - 1] ?? null;

        if ($tip === null) {
            return null;
        }

        $approach = self::bearing($tip, $next);
        $revisit = null;
        $end = $along[$count - 1] ?? 0.0;

        for ($index = $count - 2; $index >= 0; $index--) {
            $from = $stack[$index] ?? null;
            $to = $stack[$index + 1] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $travelled = $end - ($along[$index] ?? 0.0);

            if ($travelled < 60.0) {
                continue;
            }

            if ($travelled > 4_000.0) {
                break;
            }

            $turn = abs(($bearings[$index] ?? self::bearing($from, $to)) - $approach);
            $turn = min($turn, 360.0 - $turn);

            if ($turn < 150.0 || self::distanceToSegment($next, $from, $to) > 50.0) {
                continue;
            }

            $revisit = $index;
        }

        if ($revisit === null) {
            return null;
        }

        $origin = $stack[$revisit] ?? null;
        $rejoin = $stack[$revisit + 1] ?? null;

        if ($origin === null || $rejoin === null) {
            return null;
        }

        $farIndex = $revisit;
        $farDistance = 0.0;

        for ($index = $revisit; $index < $count; $index++) {
            $point = $stack[$index] ?? null;

            if ($point === null) {
                continue;
            }

            $distance = self::metres($origin, $point);

            if ($distance > $farDistance) {
                $farDistance = $distance;
                $farIndex = $index;
            }
        }

        $far = $stack[$farIndex] ?? null;

        if ($far === null || $farDistance < 40.0) {
            return null;
        }

        $onRoad = 0.0;
        $samples = 0.0;

        for ($index = $revisit; $index < $count - 1; $index++) {
            $from = $stack[$index] ?? null;
            $to = $stack[$index + 1] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $length = self::metres($from, $to);
            $samples += $length;
            $midpoint = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];

            if (self::distanceToSegment($midpoint, $origin, $far) <= 55.0) {
                $onRoad += $length;
            }
        }

        // A hook at the far end sits off the straight line. A mid-route spur is still
        // a turn-back. A match on the opening of the loop is a circuit closing, and
        // only a very straight excursion may be removed there.
        $opensAtStart = $revisit <= max(2, (int) ($count * 0.15));
        $minimum = $opensAtStart ? 0.65 : 0.18;

        if ($samples < 40.0 || ($onRoad / $samples) < $minimum) {
            return null;
        }

        $join = self::closestPointOnSegment($next, $origin, $rejoin);

        return [
            'keep' => $revisit + 1,
            'join' => self::metres($origin, $join) >= 8.0 ? $join : null,
        ];
    }

    /**
     * Puts the finish back on the pin when a cut left the line ending up the road.
     * The original loop already closed. The trimmed one must too.
     *
     * @param  list<array{0: float, 1: float}>  $original
     * @param  list<array{0: float, 1: float}>  $stack
     * @return list<array{0: float, 1: float}>
     */
    private static function closeToStart(array $original, array $stack): array
    {
        $start = $original[0] ?? null;
        $originalEnd = array_last($original) ?? null;
        $trimmedEnd = array_last($stack) ?? null;

        if ($start === null || $originalEnd === null || $trimmedEnd === null) {
            return $stack;
        }

        if (self::metres($start, $originalEnd) > 80.0 || self::metres($start, $trimmedEnd) <= 200.0) {
            return $stack;
        }

        $resume = null;
        $from = (int) (count($original) * 0.5);

        for ($index = count($original) - 1; $index >= $from; $index--) {
            $point = $original[$index] ?? null;

            if ($point !== null && self::metres($trimmedEnd, $point) <= 30.0) {
                $resume = $index;
                break;
            }
        }

        if ($resume === null) {
            return $stack;
        }

        foreach (array_slice($original, $resume + 1) as $point) {
            $tip = array_last($stack) ?? null;

            if ($tip !== null && self::metres($tip, $point) >= 8.0) {
                $stack[] = $point;
            }
        }

        return $stack;
    }

    /**
     * If the finish rides back along the same road the loop started on, drop that return.
     * The line keeps the way out and no longer draws both directions.
     *
     * @param  list<array{0: float, 1: float}>  $stack
     * @return list<array{0: float, 1: float}>
     */
    private static function peelClosingRetrace(array $stack): array
    {
        $floor = max(4, (int) (count($stack) * 0.75));

        while (count($stack) > $floor) {
            $last = count($stack) - 1;
            $end = $stack[$last];
            $before = $stack[$last - 1];
            $back = self::bearing($before, $end);
            $peeled = false;
            $opening = min($last - 1, 30);

            for ($index = 0; $index < $opening; $index++) {
                $from = $stack[$index] ?? null;
                $to = $stack[$index + 1] ?? null;

                if ($from === null || $to === null) {
                    continue;
                }

                $turn = abs(self::bearing($from, $to) - $back);
                $turn = min($turn, 360.0 - $turn);

                if ($turn < 150.0) {
                    continue;
                }

                if (self::distanceToSegment($end, $from, $to) > 20.0 || self::distanceToSegment($before, $from, $to) > 20.0) {
                    continue;
                }

                array_pop($stack);
                $peeled = true;
                break;
            }

            if (! $peeled) {
                break;
            }
        }

        return $stack;
    }

    /**
     * @param  list<GeneratedRoute>  $routes
     */
    public static function cleanest(array $routes, int $targetMetres): ?GeneratedRoute
    {
        $ranked = self::ranked($routes, $targetMetres);

        return $ranked[0] ?? null;
    }

    /**
     * Lowest score first. A road ridden back down, anywhere but the start
     * street, loses to a clean loop, even when the clean loop is a little
     * off the requested length.
     *
     * @param  list<GeneratedRoute>  $routes
     * @return list<GeneratedRoute>
     */
    public static function ranked(array $routes, int $targetMetres): array
    {
        return array_column(self::scored($routes, $targetMetres), 'route');
    }

    /**
     * Each route with its score and its metres of doubled-back road, best
     * first. Scoring once per route keeps the sort from measuring the same
     * line again on every comparison.
     *
     * @param  list<GeneratedRoute>  $routes
     * @return list<array{route: GeneratedRoute, score: float, doubles: bool}>
     */
    private static function scored(array $routes, int $targetMetres): array
    {
        $ascents = array_map(fn (GeneratedRoute $generatedRoute): int => $generatedRoute->ascentMetres, $routes);
        $least = $ascents === [] ? 0 : min($ascents);
        $most = $ascents === [] ? 0 : max($ascents);

        $scored = array_map(function (GeneratedRoute $generatedRoute) use ($targetMetres, $least, $most): array {
            ['away' => $doubled, 'total' => $total] = self::reversal($generatedRoute->coordinates, self::startOf($generatedRoute));
            $doubles = $doubled > self::doubledLimit($total);
            $climb = self::climbCost($generatedRoute, $targetMetres, $least, $most);

            return [
                'route' => $generatedRoute,
                'score' => self::scoreWith($generatedRoute, $targetMetres, $doubled, $doubles, $total) + $climb,
                'doubles' => $doubles,
            ];
        }, $routes);

        usort($scored, static fn (array $left, array $right): int => $left['score'] <=> $right['score']);

        return $scored;
    }

    /**
     * Up to three loops that are not the same line, best first. After the
     * best, the clean loops within lengthTolerance() of the length come
     * first. When there are not enough of them and $fill is on, the next
     * best fill the rest, so a rider is offered three loops whenever three
     * different lines were found.
     *
     * @param  list<GeneratedRoute>  $routes
     * @return list<GeneratedRoute>
     */
    public static function diverse(array $routes, int $targetMetres, int $limit = 3, bool $fill = true): array
    {
        $scored = self::scored($routes, $targetMetres);
        $tolerance = self::lengthTolerance($targetMetres);
        $kept = [];

        // On the length first, then the rest in their order: a loop near the
        // length that rides back down a road before a clean one far off it.
        foreach ($fill ? ['on length', 'any'] : ['on length'] as $pass) {
            foreach ($scored as ['route' => $generatedRoute, 'doubles' => $doubles]) {
                if (count($kept) === $limit) {
                    return $kept;
                }

                $onLength = ! $doubles && abs($generatedRoute->distanceMetres - $targetMetres) <= $tolerance && self::onItsSide($generatedRoute, $targetMetres);
                if ($kept !== [] && $pass === 'on length' && ! $onLength) {
                    continue;
                }

                if (array_any($kept, fn (GeneratedRoute $existing): bool => $existing === $generatedRoute || self::alike($existing, $generatedRoute))) {
                    continue;
                }

                $kept[] = $generatedRoute;
            }
        }

        return $kept;
    }

    public static function score(GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        return self::scored([$generatedRoute], $targetMetres)[0]['score'] ?? INF;
    }

    /**
     * Tiers, each far apart so nothing within one reaches the next: a clean
     * loop near the length, then one that rides back down a road, then a
     * clean loop far off the length, then one on the wrong side of a most or
     * a least. A rider asking for 100 km is shown
     * the 100 km loop with a stretch back down a road before a 143 km one.
     */
    private static function scoreWith(GeneratedRoute $generatedRoute, int $targetMetres, float $doubled, bool $doubles, float $lineMetres): float
    {
        $error = self::lengthError($generatedRoute, $targetMetres);

        // A rider who asked for at most or at least the length is shown a
        // loop over or under it only when nothing else was found.
        if (! self::onItsSide($generatedRoute, $targetMetres)) {
            return 3_000 + $error;
        }

        // Among loops that ride back down a road, the least doubled road
        // wins: each doubled kilometre weighs like being 100% off a short loop.
        if ($doubles) {
            return 1_000 + ($doubled / 1_000) + $error;
        }

        if ($error > 0.35) {
            return 2_000 + $error;
        }

        // Among clean lines the closest length wins, with the doubled metres on top.
        return $error + self::doubledCost($doubled, $lineMetres);
    }

    /**
     * How far off the length a loop may come out and still be on it: 15% of
     * a short loop, and a smaller share of a longer one. Being 15% off is
     * 1.5 km on a 10 km run, but 15 km on a 100 km ride.
     */
    public static function lengthTolerance(int $targetMetres): float
    {
        $metres = max(1, $targetMetres);

        return min(self::LENGTH_SHARE * $metres, self::LENGTH_METRES_PER_ROOT_KM * sqrt($metres / 1_000));
    }

    /**
     * The length the loops are aimed at: the length itself, or half a
     * tolerance inside it when it is a most or a least, so that more of the
     * loops land on the side the rider asked for.
     */
    public static function aimMetres(RouteQuery $routeQuery): int
    {
        $half = (int) round(self::lengthTolerance($routeQuery->distanceMetres) / 2);

        return match ($routeQuery->lengthBias) {
            LengthBias::Closest => $routeQuery->distanceMetres,
            LengthBias::AtMost => $routeQuery->distanceMetres - $half,
            LengthBias::AtLeast => $routeQuery->distanceMetres + $half,
        };
    }

    /** Whether a loop is on the side of the length the rider asked for. */
    private static function onItsSide(GeneratedRoute $generatedRoute, int $targetMetres): bool
    {
        return match ($generatedRoute->query->lengthBias) {
            LengthBias::Closest => true,
            LengthBias::AtMost => $generatedRoute->distanceMetres <= $targetMetres,
            LengthBias::AtLeast => $generatedRoute->distanceMetres >= $targetMetres,
        };
    }

    /**
     * How far off the length, measured so that a loop at its tolerance is
     * LENGTH_SHARE off whatever its length. A short loop is measured as a
     * share of its length; a long one is held closer.
     */
    private static function lengthError(GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        return abs($generatedRoute->distanceMetres - $targetMetres) / self::lengthTolerance($targetMetres) * self::LENGTH_SHARE;
    }

    /**
     * Flat weighs the climb a kilometre above the flattest loop, hilly the
     * climb a kilometre below the hilliest, and neutral leaves it out. The
     * kilometres are the ones the rider asked for, so a loop that runs long
     * does not water its climb down with flat miles. Measured from the other
     * loops, the climb tells loops apart in the hills as well as on a plain.
     * A line whose heights did not come back climbs 0 m, so among such lines
     * the climb changes nothing.
     */
    private static function climbCost(GeneratedRoute $generatedRoute, int $targetMetres, int $least, int $most): float
    {
        $extra = match ($generatedRoute->query->elevation) {
            ElevationBias::Minimize => $generatedRoute->ascentMetres - $least,
            ElevationBias::Maximize => $most - $generatedRoute->ascentMetres,
            ElevationBias::Neutral => 0,
        };

        return min(self::LENGTH_SHARE, $extra / max(1.0, $targetMetres / 1_000) / self::CLIMB_PER_KM_WEIGHT);
    }

    /**
     * Where the rider asked the loop to start, which the line passes near.
     *
     * @return array{0: float, 1: float}
     */
    private static function startOf(GeneratedRoute $generatedRoute): array
    {
        return [$generatedRoute->query->startLongitude, $generatedRoute->query->startLatitude];
    }

    /**
     * @param  list<array{from: array{0: float, 1: float}, to: array{0: float, 1: float}, fromLat: int, fromLon: int, toLat: int, toLon: int, bearing: float}>  $used
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function reverses(
        array $used,
        array $from,
        array $to,
        int $fromLat,
        int $fromLon,
        int $toLat,
        int $toLon,
        float $bearing,
    ): bool {
        $midpoint = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];

        foreach ($used as $edge) {
            $turn = abs($bearing - $edge['bearing']);
            $turn = min($turn, 360.0 - $turn);

            if ($turn < 150.0) {
                continue;
            }

            $leavesWhereItArrived = abs($edge['toLat'] - $fromLat) <= 2 && abs($edge['toLon'] - $fromLon) <= 2;
            $arrivesWhereItLeft = abs($edge['fromLat'] - $toLat) <= 2 && abs($edge['fromLon'] - $toLon) <= 2;

            if ($leavesWhereItArrived && $arrivesWhereItLeft) {
                return true;
            }

            if (self::distanceToSegment($midpoint, $edge['from'], $edge['to']) <= 40.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     */
    private static function distanceToSegment(array $point, array $start, array $end): float
    {
        $closest = self::closestPointOnSegment($point, $start, $end);
        $latScale = 111_320.0;
        $lonScale = 111_320.0 * cos(deg2rad($point[1]));

        return hypot(($point[0] - $closest[0]) * $lonScale, ($point[1] - $closest[1]) * $latScale);
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  array{0: float, 1: float}  $start
     * @param  array{0: float, 1: float}  $end
     * @return array{0: float, 1: float}
     */
    private static function closestPointOnSegment(array $point, array $start, array $end): array
    {
        $latScale = 111_320.0;
        $lonScale = max(1.0, 111_320.0 * cos(deg2rad($point[1])));
        $px = $point[0] * $lonScale;
        $py = $point[1] * $latScale;
        $ax = $start[0] * $lonScale;
        $ay = $start[1] * $latScale;
        $bx = $end[0] * $lonScale;
        $by = $end[1] * $latScale;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = ($dx * $dx) + ($dy * $dy);

        if ($lengthSquared < 1.0) {
            return $start;
        }

        $progress = max(0.0, min(1.0, ((($px - $ax) * $dx) + (($py - $ay) * $dy)) / $lengthSquared));

        return [($ax + ($progress * $dx)) / $lonScale, ($ay + ($progress * $dy)) / $latScale];
    }

    /** Grid cells about 200 m across, for finding nearby segments. */
    private const float GRID = 0.002;

    /** Wider than the 40 m and two-cell matches in reverses(), so no match is missed. */
    private const float MARGIN = 0.0015;

    /**
     * @param  array{0: float, 1: float}  $point
     */
    private static function gridKey(array $point): string
    {
        return ((int) floor($point[0] / self::GRID)).':'.((int) floor($point[1] / self::GRID));
    }

    /**
     * Every grid cell a segment, padded by the margin, touches.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @return list<string>
     */
    private static function gridKeysNear(array $from, array $to): array
    {
        $west = (int) floor((min($from[0], $to[0]) - self::MARGIN) / self::GRID);
        $east = (int) floor((max($from[0], $to[0]) + self::MARGIN) / self::GRID);
        $south = (int) floor((min($from[1], $to[1]) - self::MARGIN) / self::GRID);
        $north = (int) floor((max($from[1], $to[1]) + self::MARGIN) / self::GRID);
        $keys = [];

        for ($x = $west; $x <= $east; $x++) {
            for ($y = $south; $y <= $north; $y++) {
                $keys[] = $x.':'.$y;
            }
        }

        return $keys;
    }

    private static function cell(float $degrees, float $size): int
    {
        return (int) round($degrees / $size);
    }

    /**
     * Two loops are the same line when they are near the same length and pass
     * the same places a quarter, half, and three quarters of the way round.
     * The middle alone is not enough: loops through a via point all pass it
     * near halfway, though they reach it from different sides.
     */
    private static function alike(GeneratedRoute $left, GeneratedRoute $right): bool
    {
        $lengthGap = abs($left->distanceMetres - $right->distanceMetres) / max(1, $left->distanceMetres);

        if ($lengthGap >= 0.08) {
            return false;
        }

        foreach ([0.25, 0.5, 0.75] as $share) {
            $leftPoint = $left->coordinates[(int) (count($left->coordinates) * $share)] ?? null;
            $rightPoint = $right->coordinates[(int) (count($right->coordinates) * $share)] ?? null;

            if ($leftPoint === null || $rightPoint === null || self::metres($leftPoint, $rightPoint) >= 400.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function metres(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($to[0] - $from[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function bearing(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLon = deg2rad($to[0] - $from[0]);
        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
