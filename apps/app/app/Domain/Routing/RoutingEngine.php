<?php

declare(strict_types=1);

namespace App\Domain\Routing;

interface RoutingEngine
{
    public function route(RouteQuery $query): GeneratedRoute;

    /**
     * @return list<GeneratedRoute>
     */
    public function candidates(RouteQuery $query): array;

    /**
     * A line through the given points, in order. The first point is the start.
     *
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public function trace(RouteQuery $query, array $waypoints): GeneratedRoute;

    /**
     * A way from the last point back to the start that does not turn around
     * on the roads already ridden.
     *
     * @param  list<array{0: float, 1: float}>  $ridden
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    public function closeLoop(RouteQuery $query, array $ridden, array $from, array $to): GeneratedRoute;
}
