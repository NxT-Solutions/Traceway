<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use RuntimeException;

/**
 * Draws with the main engine, and with a backup only while the main one is
 * busy or turns Traceway away. A main engine that answers "no road fits" is
 * believed: the backup is not asked to second-guess it. The backup's routes
 * come without the climb, since it reads no heights.
 */
final readonly class FallbackRoutingEngine implements LineFollower, RoutingEngine
{
    public function __construct(
        private RoutingEngine&LineFollower $main,
        private RoutingEngine $backup,
    ) {}

    public function route(RouteQuery $routeQuery): GeneratedRoute
    {
        return $this->either(
            fn (): GeneratedRoute => $this->main->route($routeQuery),
            fn (): GeneratedRoute => $this->backup->route($routeQuery),
        );
    }

    public function candidates(RouteQuery $routeQuery): array
    {
        return $this->either(
            fn (): array => $this->main->candidates($routeQuery),
            fn (): array => $this->backup->candidates($routeQuery),
        );
    }

    public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
    {
        return $this->either(
            fn (): GeneratedRoute => $this->main->trace($routeQuery, $waypoints),
            fn (): GeneratedRoute => $this->backup->trace($routeQuery, $waypoints),
        );
    }

    public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
    {
        return $this->either(
            fn (): GeneratedRoute => $this->main->closeLoop($routeQuery, $ridden, $from, $to),
            fn (): GeneratedRoute => $this->backup->closeLoop($routeQuery, $ridden, $from, $to),
        );
    }

    /**
     * Only the main engine snaps a whole line. When it cannot, the caller
     * follows the line through its points, which the backup can do.
     */
    public function match(RouteQuery $routeQuery, array $line): ?GeneratedRoute
    {
        return $this->main->match($routeQuery, $line);
    }

    public function follow(RouteQuery $routeQuery, array $points): GeneratedRoute
    {
        return $this->either(
            fn (): GeneratedRoute => $this->main->follow($routeQuery, $points),
            fn (): GeneratedRoute => new TracingLineFollower($this->backup)->follow($routeQuery, $points),
        );
    }

    /**
     * When the backup cannot draw it either, the rider still hears that the
     * map server is busy, not that the place has no roads.
     *
     * @template T
     *
     * @param  callable(): T  $main
     * @param  callable(): T  $backup
     * @return T
     */
    private function either(callable $main, callable $backup): mixed
    {
        try {
            return $main();
        } catch (MapServerBusy $mapServerBusy) {
            try {
                return $backup();
            } catch (RuntimeException $runtimeException) {
                throw new MapServerBusy($mapServerBusy->getMessage(), $mapServerBusy->getCode(), previous: $runtimeException);
            }
        }
    }
}
