<?php

declare(strict_types=1);

namespace App\Integrations\GraphHopper;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteQuery;
use App\Domain\Routing\RoutingEngine;
use RuntimeException;

final class GraphHopperRoutingEngine implements RoutingEngine
{
    public function __construct(private readonly GraphHopperConnector $connector) {}

    public function route(RouteQuery $query): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $query->sport->value,
        ));
    }

    public function candidates(RouteQuery $query): array
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $query->sport->value,
        ));
    }

    public function trace(RouteQuery $query, array $waypoints): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $query->sport->value,
        ));
    }

    public function closeLoop(RouteQuery $query, array $ridden, array $from, array $to): GeneratedRoute
    {
        throw new RuntimeException(sprintf(
            'Route generation for %s starts once a GraphHopper graph is imported.',
            $query->sport->value,
        ));
    }

    public function connector(): GraphHopperConnector
    {
        return $this->connector;
    }
}
