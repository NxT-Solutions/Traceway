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
     * legs before it.
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
                        'outside' => $reversed && $start !== null ? self::offStartStreet($from, $coordinate, $metres, $start) : $metres,
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

        // Only a line that comes back to where it started has a ride home.
        $first = $coordinates[0] ?? null;
        $last = array_last($coordinates);
        $home = $last !== null && (self::metres($start, $last) <= self::START_STREET_METRES
            || ($first !== null && self::metres($first, $last) <= self::START_STREET_METRES));

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
     * The metres of a segment farther than the start street from the start,
     * measured in short pieces so a long segment from the pin is not counted
     * all in or all out.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     * @param  array{0: float, 1: float}  $start
     */
    private static function offStartStreet(array $from, array $to, float $metres, array $start): float
    {
        $pieces = max(1, (int) ceil($metres / 25.0));
        $outside = 0.0;

        for ($piece = 0; $piece < $pieces; $piece++) {
            $along = ($piece + 0.5) / $pieces;
            $point = [$from[0] + (($to[0] - $from[0]) * $along), $from[1] + (($to[1] - $from[1]) * $along)];

            if (self::metres($start, $point) > self::START_STREET_METRES) {
                $outside += $metres / $pieces;
            }
        }

        return $outside;
    }

    /**
     * Drops a stretch that leaves a point and comes straight back along the same road.
     * A real corner stays. An out-and-back spur, like the one east of Bergom, does not.
     *
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @return list<array{0: float, 1: float}>
     */
    public static function withoutSpurs(array $coordinates): array
    {
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

        if (count($stack) < 2 || count($stack) < (int) (count($coordinates) * 0.2)) {
            return $coordinates;
        }

        return $stack;
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
        $scored = array_map(function (GeneratedRoute $generatedRoute) use ($targetMetres): array {
            ['away' => $doubled, 'total' => $total] = self::reversal($generatedRoute->coordinates, self::startOf($generatedRoute));
            $doubles = $doubled > self::doubledLimit($total);

            return [
                'route' => $generatedRoute,
                'score' => self::scoreWith($generatedRoute, $targetMetres, $doubled, $doubles, $total),
                'doubles' => $doubles,
            ];
        }, $routes);

        usort($scored, static fn (array $left, array $right): int => $left['score'] <=> $right['score']);

        return $scored;
    }

    /**
     * Up to three loops that are not the same line.
     *
     * @param  list<GeneratedRoute>  $routes
     * @return list<GeneratedRoute>
     */
    public static function diverse(array $routes, int $targetMetres, int $limit = 3): array
    {
        $kept = [];

        foreach (self::scored($routes, $targetMetres) as ['route' => $generatedRoute, 'doubles' => $doubles]) {
            if ($kept !== [] && $doubles) {
                continue;
            }

            $duplicate = array_any($kept, fn (GeneratedRoute $existing): bool => self::alike($existing, $generatedRoute));

            if ($duplicate) {
                continue;
            }

            $kept[] = $generatedRoute;

            if (count($kept) === $limit) {
                break;
            }
        }

        return $kept;
    }

    public static function score(GeneratedRoute $generatedRoute, int $targetMetres): float
    {
        return self::scored([$generatedRoute], $targetMetres)[0]['score'] ?? INF;
    }

    private static function scoreWith(GeneratedRoute $generatedRoute, int $targetMetres, float $doubled, bool $doubles, float $lineMetres): float
    {
        $error = abs($generatedRoute->distanceMetres - $targetMetres) / max(1, $targetMetres);

        // Back down the same road still loses, on a long loop as on a short one.
        if ($doubles) {
            return 2 + ($doubled / 10_000) + $error;
        }

        if ($error > 0.35) {
            return 5 + $error;
        }

        // Among clean lines the closest length wins, with the doubled metres on top.
        return $error + self::doubledCost($doubled, $lineMetres);
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

    private static function alike(GeneratedRoute $left, GeneratedRoute $right): bool
    {
        $leftMid = $left->coordinates[(int) (count($left->coordinates) / 2)] ?? null;
        $rightMid = $right->coordinates[(int) (count($right->coordinates) / 2)] ?? null;

        if ($leftMid === null || $rightMid === null) {
            return false;
        }

        $apart = self::metres($leftMid, $rightMid);
        $lengthGap = abs($left->distanceMetres - $right->distanceMetres) / max(1, $left->distanceMetres);

        return $apart < 400.0 && $lengthGap < 0.08;
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
