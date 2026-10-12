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
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public static function reverseShare(array $coordinates): float
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
        $count = count($coordinates);

        for ($index = 1; $index < $count; $index++) {
            $from = $coordinates[$index - 1] ?? null;
            $to = $coordinates[$index] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $metres = self::metres($from, $to);

            if ($metres < 8.0) {
                continue;
            }

            $totalMetres += $metres;
            $bearing = self::bearing($from, $to);
            $fromLat = self::cell($from[1], 0.00035);
            $fromLon = self::cell($from[0], 0.00045);
            $toLat = self::cell($to[1], 0.00035);
            $toLon = self::cell($to[0], 0.00045);

            $midpoint = [($from[0] + $to[0]) / 2, ($from[1] + $to[1]) / 2];
            $close = [];

            foreach ([$from, $to, $midpoint] as $point) {
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

            if (self::reverses($candidates, $from, $to, $fromLat, $fromLon, $toLat, $toLon, $bearing)) {
                $reversedMetres += $metres;
            }

            foreach (self::gridKeysNear($from, $to) as $key) {
                $nearby[$key][] = count($used);
            }

            $used[] = [
                'from' => $from,
                'to' => $to,
                'fromLat' => $fromLat,
                'fromLon' => $fromLon,
                'toLat' => $toLat,
                'toLon' => $toLon,
                'bearing' => $bearing,
            ];
        }

        if ($totalMetres === 0.0) {
            return 0.0;
        }

        return $reversedMetres / $totalMetres;
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
        $stack = [];

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

                $turn = abs(self::bearing($previous, $tip) - self::bearing($tip, $coordinate));
                $turn = min($turn, 360.0 - $turn);

                if ($turn < 150.0 || self::distanceToSegment($coordinate, $previous, $tip) > 40.0) {
                    break;
                }

                array_pop($stack);
            }

            $cut = self::cutThinReturn($stack, $coordinate);

            if ($cut !== $stack) {
                $stack = $cut;

                continue;
            }

            if ($stack === []) {
                $stack[] = $coordinate;

                continue;
            }

            $tip = array_last($stack);

            if (self::metres($tip, $coordinate) >= 8.0) {
                $stack[] = $coordinate;
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
     * @param  list<array{0: float, 1: float}>  $stack
     * @param  array{0: float, 1: float}  $next
     * @return list<array{0: float, 1: float}>
     */
    private static function cutThinReturn(array $stack, array $next): array
    {
        $count = count($stack);

        if ($count < 4) {
            return $stack;
        }

        $tip = $stack[$count - 1] ?? null;

        if ($tip === null) {
            return $stack;
        }

        $approach = self::bearing($tip, $next);
        $revisit = null;
        $travelled = 0.0;

        for ($index = $count - 2; $index >= 0; $index--) {
            $from = $stack[$index] ?? null;
            $to = $stack[$index + 1] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $travelled += self::metres($from, $to);

            if ($travelled < 60.0) {
                continue;
            }

            if ($travelled > 4_000.0) {
                break;
            }

            $turn = abs(self::bearing($from, $to) - $approach);
            $turn = min($turn, 360.0 - $turn);

            if ($turn < 150.0 || self::distanceToSegment($next, $from, $to) > 50.0) {
                continue;
            }

            $revisit = $index;
        }

        if ($revisit === null) {
            return $stack;
        }

        $origin = $stack[$revisit] ?? null;
        $rejoin = $stack[$revisit + 1] ?? null;

        if ($origin === null || $rejoin === null) {
            return $stack;
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
            return $stack;
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
            return $stack;
        }

        $kept = array_slice($stack, 0, $revisit + 1);
        $join = self::closestPointOnSegment($next, $origin, $rejoin);
        $keptTip = array_last($kept) ?? null;

        if ($keptTip !== null && self::metres($keptTip, $join) >= 8.0) {
            $kept[] = $join;
        }

        return $kept;
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
     * Lowest score first. A road used in both directions loses to a clean
     * loop, even when the clean loop is a little off the requested length.
     *
     * @param  list<GeneratedRoute>  $routes
     * @return list<GeneratedRoute>
     */
    public static function ranked(array $routes, int $targetMetres): array
    {
        usort(
            $routes,
            static fn (GeneratedRoute $left, GeneratedRoute $right): int => self::score($left, $targetMetres) <=> self::score($right, $targetMetres),
        );

        return $routes;
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

        foreach (self::ranked($routes, $targetMetres) as $generatedRoute) {
            if ($kept !== [] && self::reverseShare($generatedRoute->coordinates) > 0.12) {
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
        $error = abs($generatedRoute->distanceMetres - $targetMetres) / max(1, $targetMetres);
        $overlap = self::reverseShare($generatedRoute->coordinates);

        // A same-street double still loses. Among clean lines, the closest length wins.
        if ($overlap > 0.12) {
            return 2 + $overlap + $error;
        }

        if ($error > 0.35) {
            return 5 + $error;
        }

        return $error + ($overlap * 0.4);
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
