<?php

declare(strict_types=1);

namespace App\Integrations\GraphHopper;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use RuntimeException;

final readonly class GraphHopperRoutingEngine implements RoutingEngine
{
    public function __construct(private GraphHopperConnector $graphHopperConnector) {}

    public function route(RouteQuery $routeQuery): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $routeQuery->sport->value,
        ));
    }

    public function candidates(RouteQuery $routeQuery): array
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $routeQuery->sport->value,
        ));
    }

    public function trace(RouteQuery $routeQuery, array $waypoints): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $routeQuery->sport->value,
        ));
    }

    public function closeLoop(RouteQuery $routeQuery, array $ridden, array $from, array $to): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $routeQuery->sport->value,
        ));
    }

    public function connector(): GraphHopperConnector
    {
        return $this->graphHopperConnector;
    }
}
