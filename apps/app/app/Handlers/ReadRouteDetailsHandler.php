<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\ReadRouteDetailsCommand;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use App\Routes\SavedLeg;
use Throwable;

/**
 * Reads the climb, the turns, and the stops of a route saved without them,
 * and keeps them. A try that fails is noted, so the next run starts with
 * the routes not tried for longest.
 */
final readonly class ReadRouteDetailsHandler
{
    public function __construct(
        private SavedRouteRepository $savedRouteRepository,
        private RouteDetailsSource $routeDetailsSource,
    ) {}

    /**
     * True when every part came back. Otherwise what was read is kept, and
     * the rest waits for a later run.
     */
    public function handle(ReadRouteDetailsCommand $readRouteDetailsCommand): bool
    {
        $route = $readRouteDetailsCommand->unreadRoute;

        try {
            if ($route->legs === []) {
                $routeDetails = $this->routeDetailsSource->for($route->sport, $route->line);

                if (! $routeDetails->complete) {
                    $this->savedRouteRepository->noteDetailsTried($route->routeId);

                    return false;
                }

                $this->savedRouteRepository->storeDetails($route->routeId, $routeDetails->toArray(), [], $routeDetails->hasClimb() ? $routeDetails->ascentMetres : null);

                return true;
            }

            $complete = true;
            $legs = [];

            foreach ($route->legs as $savedLeg) {
                if (! $savedLeg->sport instanceof Sport || $savedLeg->details instanceof RouteDetails) {
                    $legs[] = $savedLeg;

                    continue;
                }

                $routeDetails = $this->routeDetailsSource->for($savedLeg->sport, $savedLeg->coordinates);
                $complete = $complete && $routeDetails->complete;
                $legs[] = $routeDetails->complete ? $savedLeg->withDetails($routeDetails) : $savedLeg;
            }

            $this->savedRouteRepository->storeDetails(
                $route->routeId,
                details: null,
                legs: $legs,
                ascentMetres: array_sum(array_map(fn (SavedLeg $savedLeg): int => $savedLeg->ascentMetres, $legs)),
            );

            return $complete;
        } catch (Throwable $throwable) {
            $this->savedRouteRepository->noteDetailsTried($route->routeId);

            throw $throwable;
        }
    }
}
