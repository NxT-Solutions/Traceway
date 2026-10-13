<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Follows a line with any routing engine, through its points. An engine
 * that cannot snap a line or hold a heading at a point uses this.
 */
final readonly class TracingLineFollower implements LineFollower
{
    public function __construct(private RoutingEngine $routingEngine) {}

    public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute
    {
        return null;
    }

    /**
     * @param  list<FollowPoint>  $points
     */
    public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute
    {
        return $this->routingEngine->trace(
            $routeQuery,
            array_map(fn (FollowPoint $followPoint): array => $followPoint->point, $points),
        );
    }
}
