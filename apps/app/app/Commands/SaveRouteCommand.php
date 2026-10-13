<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Layout;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Routes\CoordinateLine;
use App\Routes\RouteName;
use App\Routes\SavedLeg;

final readonly class SaveRouteCommand
{
    /**
     * @param  list<SavedLeg>  $legs  The legs of a multisport plan, in order. Empty for a single sport.
     */
    public function __construct(
        public RiderId $riderId,
        public RouteName $name,
        public Sport $sport,
        public Surface $surface,
        public ElevationBias $elevation,
        public string $roads,
        public int $distanceMetres,
        public int $ascentMetres,
        public CoordinateLine $coordinates,
        public ?CoordinateLine $userCoordinates,
        public ?CoordinateLine $finishCoordinates,
        public ?Layout $layout = null,
        public array $legs = [],
        public ?RouteDetails $details = null,
    ) {}

    /**
     * The same save with the climb, surfaces, turns, and stops read from the
     * map. The climb read from the ground replaces the one the planner sent.
     *
     * @param  list<SavedLeg>  $legs
     */
    public function withDetails(?RouteDetails $routeDetails, array $legs): self
    {
        return new self(
            riderId: $this->riderId,
            name: $this->name,
            sport: $this->sport,
            surface: $this->surface,
            elevation: $this->elevation,
            roads: $this->roads,
            distanceMetres: $this->distanceMetres,
            ascentMetres: $routeDetails instanceof RouteDetails ? $routeDetails->ascentMetres : $this->legAscent($legs) ?? $this->ascentMetres,
            coordinates: $this->coordinates,
            userCoordinates: $this->userCoordinates,
            finishCoordinates: $this->finishCoordinates,
            layout: $this->layout,
            legs: $legs,
            details: $routeDetails,
        );
    }

    /**
     * The climb of a multisport plan, once every land leg has been read.
     *
     * @param  list<SavedLeg>  $legs
     */
    private function legAscent(array $legs): ?int
    {
        $ascent = 0;
        $read = false;

        foreach ($legs as $leg) {
            if (! $leg->sport instanceof Sport) {
                continue;
            }

            if (! $leg->details instanceof RouteDetails) {
                return null;
            }

            $ascent += $leg->details->ascentMetres;
            $read = true;
        }

        return $read ? $ascent : null;
    }
}
