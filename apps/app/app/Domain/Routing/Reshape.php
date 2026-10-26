<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;
use RuntimeException;

/**
 * Changes a drawn route without planning it all again. The line is cut at
 * its waypoints; the legs the change touches are planned again by the
 * routing engine and the rest is kept as it was, so moving one point moves
 * only the roads around it.
 *
 * A built loop's last leg is the way home Traceway found; planned again, it
 * is found the same way, without riding back on the roads already ridden.
 */
final readonly class Reshape
{
    public const int MOST_WAYPOINTS = 14;

    /** A moved or new point farther than this from the route is not a change to it. */
    public const float FARTHEST_METRES = 30_000.0;

    public function __construct(private RoutingEngine $routingEngine) {}

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints  The points the line passes, start first.
     * @param  bool  $closing  Whether the last leg is the way home of a built loop.
     * @param  bool  $loop  Whether the line ends where it starts, so its first and last points stay.
     */
    public function apply(RouteQuery $routeQuery, array $line, array $waypoints, RouteChange $routeChange, bool $closing = false, bool $loop = false): Reshaped
    {
        throw_if(count($waypoints) < 2 || count($waypoints) > self::MOST_WAYPOINTS, InvalidArgumentException::class, 'A route to change has 2 to '.self::MOST_WAYPOINTS.' points.');
        $this->fits($line, $waypoints, $routeChange, $closing || $loop);

        // Each point as the line passes it. A loop's stops are where Traceway
        // aimed, often a field beside the road; the line is on the road. A leg
        // planned again then starts and ends where the kept ones do.
        $cuts = LegSplitter::cuts($line, $waypoints);
        $waypoints = array_map(fn (int $cut): array => $line[$cut], $cuts);
        [$waypoints, $legs] = $this->changed($waypoints, LegSplitter::legs($line, $waypoints), $routeChange);

        throw_if(count($waypoints) > self::MOST_WAYPOINTS, InvalidArgumentException::class, 'A route has at most '.self::MOST_WAYPOINTS.' points.');

        $changed = array_keys(array_filter($legs, fn (?array $leg): bool => $leg === null));

        foreach ($this->runs($changed) as [$first, $lastLeg]) {
            $planned = $this->plan($routeQuery, $waypoints, $legs, $first, $lastLeg, $closing && $lastLeg === count($legs) - 1);

            foreach ($planned as $offset => $leg) {
                $legs[$first + $offset] = $leg;
            }
        }

        $planned = [];

        foreach ($legs as $leg) {
            throw_if($leg === null, RuntimeException::class, 'A leg was not planned again.');
            $planned[] = $leg;
        }

        $coordinates = $this->joined($planned);

        return new Reshaped($coordinates, $waypoints, $changed, (int) round(Distance::along($coordinates)));
    }

    /**
     * A change the route can take: the start stays where it is, a loop's
     * finish stays on its start, nothing goes past the finish, and a new
     * place is near the route, so one change never asks the router to cross
     * a continent.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    private function fits(array $line, array $waypoints, RouteChange $routeChange, bool $closed): void
    {
        $count = count($waypoints);
        $index = $routeChange->index;
        $last = $count - 1;

        switch ($routeChange->kind) {
            case RouteChangeKind::Move:
                throw_if($index === 0 || ($closed && $index === $last), InvalidArgumentException::class, 'The start and the finish move with the start pin.');

                break;
            case RouteChangeKind::Insert:
                throw_if($closed && $index > $last, InvalidArgumentException::class, 'A new point goes before the finish.');

                break;
            case RouteChangeKind::Remove:
                throw_if($closed && ($index === $last || $count <= 3), InvalidArgumentException::class, 'The finish stays, and so do two points besides it.');

                break;
            case RouteChangeKind::Reroute:
                break;
        }

        if ($routeChange->point !== null) {
            throw_if(
                LineDistance::of($line)->project($routeChange->point)['gap'] > self::FARTHEST_METRES,
                InvalidArgumentException::class,
                'That place is too far from the route to bend it there. Draw a new route instead.',
            );
        }
    }

    /**
     * The waypoints after the change, and the legs with the ones to plan
     * again left empty.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  list<list<array{0: float, 1: float}>>  $split
     * @return array{0: list<array{0: float, 1: float}>, 1: list<list<array{0: float, 1: float}>|null>}
     */
    private function changed(array $waypoints, array $split, RouteChange $routeChange): array
    {
        $legs = $split;
        $count = count($waypoints);
        $index = $routeChange->index;

        switch ($routeChange->kind) {
            case RouteChangeKind::Move:
                throw_if($index < 0 || $index >= $count || $routeChange->point === null, InvalidArgumentException::class, 'No such point to move.');
                $waypoints[$index] = $routeChange->point;

                if ($index > 0) {
                    $legs[$index - 1] = null;
                }

                if ($index < $count - 1) {
                    $legs[$index] = null;
                }

                break;

            case RouteChangeKind::Insert:
                throw_if($index < 1 || $index > $count || $routeChange->point === null, InvalidArgumentException::class, 'A new point goes after the start.');
                array_splice($waypoints, $index, 0, [$routeChange->point]);

                if ($index === $count) {
                    $legs[] = null;
                } else {
                    array_splice($legs, $index - 1, 1, [null, null]);
                }

                break;

            case RouteChangeKind::Remove:
                throw_if($index < 1 || $index >= $count || $count < 3, InvalidArgumentException::class, 'Only a point after the start can go, and two must stay.');
                array_splice($waypoints, $index, 1);

                if ($index === $count - 1) {
                    array_pop($legs);
                } else {
                    array_splice($legs, $index - 1, 2, [null]);
                }

                break;

            case RouteChangeKind::Reroute:
                foreach ($routeChange->legs as $leg) {
                    throw_if($leg < 0 || $leg >= count($legs), InvalidArgumentException::class, 'No such leg to plan again.');
                    $legs[$leg] = null;
                }

                break;
        }

        return [$waypoints, array_values($legs)];
    }

    /**
     * Neighbouring legs to plan again go as one request, so a moved point
     * is ridden through rather than turned around at.
     *
     * @param  list<int>  $changed
     * @return list<array{0: int, 1: int}>
     */
    private function runs(array $changed): array
    {
        $runs = [];

        foreach ($changed as $leg) {
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last][1] === $leg - 1) {
                $runs[$last][1] = $leg;
            } else {
                $runs[] = [$leg, $leg];
            }
        }

        return $runs;
    }

    /**
     * The legs from first to last, planned again.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @param  array<int, list<array{0: float, 1: float}>|null>  $legs
     * @return list<list<array{0: float, 1: float}>>
     */
    private function plan(RouteQuery $routeQuery, array $waypoints, array $legs, int $first, int $last, bool $home): array
    {
        $through = array_slice($waypoints, $first, ($home ? $last : $last + 1) - $first + 1);
        $planned = [];

        if (count($through) >= 2) {
            $line = $this->routingEngine->trace($routeQuery, $through)->coordinates;
            $planned = LegSplitter::legs($line, $through);
        }

        if ($home) {
            $before = [];

            for ($index = 0; $index < $first; $index++) {
                $leg = $legs[$index] ?? null;

                if ($leg !== null) {
                    $before[] = $leg;
                }
            }

            $ridden = $this->joined(array_merge($before, $planned));
            $from = $waypoints[$last];
            $to = $waypoints[$last + 1] ?? $waypoints[0];
            $planned[] = $this->routingEngine->closeLoop($routeQuery, $ridden === [] ? [$from] : $ridden, $from, $to)->coordinates;
        }

        throw_if(count($planned) !== $last - $first + 1, RuntimeException::class, 'The router did not plan every leg again.');

        return $planned;
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $legs
     * @return list<array{0: float, 1: float}>
     */
    private function joined(array $legs): array
    {
        $line = [];

        foreach ($legs as $leg) {
            $line = $line === [] ? $leg : array_merge($line, array_slice($leg, 1));
        }

        return $line;
    }
}
