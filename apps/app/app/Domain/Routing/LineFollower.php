<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Rides a line someone already has, such as a GPX file, along the roads for
 * the sport, the way the line goes.
 */
interface LineFollower
{
    /**
     * Snaps the whole line onto the roads at once, or null when the engine
     * cannot. The answer may cover only part of the line, so the caller
     * checks it.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute;

    /**
     * Routes through points along the line, in order, holding the heading
     * at each point that has one.
     *
     * @param  list<FollowPoint>  $points
     */
    public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute;
}
