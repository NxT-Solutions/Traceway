<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use RuntimeException;

/**
 * A map server that does not answer. Tests never reach the real one.
 */
final class UnavailableRouteDetails implements RouteDetailsSource
{
    public function for(Sport $sport, array $line): RouteDetails
    {
        throw new RuntimeException('Route details are not available in tests.');
    }

    public function road(Sport $sport, array $line): RouteDetails
    {
        throw new RuntimeException('Route details are not available in tests.');
    }

    public function stops(Sport $sport, array $line): array
    {
        throw new RuntimeException('Stops are not available in tests.');
    }
}
