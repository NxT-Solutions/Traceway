<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\SaveRouteCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\SavedRouteRepository;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use App\Queries\Routes\SignedRouteLink;
use App\Routes\SavedLeg;
use App\Routes\SavedRouteLink;
use Throwable;

final readonly class SaveRouteHandler
{
    /**
     * How long a save waits on the map servers for its legs. Past it, the
     * rest save without details, and `routes:details` reads them later.
     */
    private const float DETAILS_SECONDS = 20.0;

    public function __construct(
        private CurrentRider $currentRider,
        private SavedRouteRepository $savedRouteRepository,
        private RouteDetailsSource $routeDetailsSource,
    ) {}

    public function handle(SaveRouteCommand $saveRouteCommand): SavedRouteLink
    {
        $this->currentRider->assertSame($saveRouteCommand->riderId);

        $id = $this->savedRouteRepository->store($this->withDetails($saveRouteCommand));

        return new SavedRouteLink($id, SignedRouteLink::share($id));
    }

    /**
     * Reads the climb, the turns, and the stops before the route is kept, so
     * the course file carries them. A single sport has them for its line. A
     * multisport plan has them for each land leg, since each leg is its own
     * course. A map server that does not answer still lets the route save.
     */
    private function withDetails(SaveRouteCommand $saveRouteCommand): SaveRouteCommand
    {
        if ($saveRouteCommand->legs === []) {
            return $saveRouteCommand->withDetails(
                $this->details($saveRouteCommand->sport, $saveRouteCommand->coordinates->points),
                [],
            );
        }

        $deadline = microtime(as_float: true) + self::DETAILS_SECONDS;
        $legs = array_map(function (SavedLeg $savedLeg) use ($deadline): SavedLeg {
            if (! $savedLeg->sport instanceof Sport || microtime(as_float: true) > $deadline) {
                return $savedLeg;
            }

            $routeDetails = $this->details($savedLeg->sport, $savedLeg->coordinates);

            return $routeDetails instanceof RouteDetails ? $savedLeg->withDetails($routeDetails) : $savedLeg;
        }, $saveRouteCommand->legs);

        return $saveRouteCommand->withDetails(routeDetails: null, legs: $legs);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    private function details(Sport $sport, array $line): ?RouteDetails
    {
        try {
            return $this->routeDetailsSource->for($sport, $line);
        } catch (Throwable) {
            return null;
        }
    }
}
