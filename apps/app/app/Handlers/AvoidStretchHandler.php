<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\AvoidStretchCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\EdgeFinder;
use App\Domain\Routing\RoadEdge;
use App\Routes\StretchToAvoid;
use App\Routes\TooManyAvoidedStretches;
use RuntimeException;

final readonly class AvoidStretchHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private EdgeFinder $edgeFinder,
        private AvoidedStretchRepository $avoidedStretchRepository,
    ) {}

    /**
     * The stretch now skipped, or null when no way the sport may use is
     * close enough to the tap.
     *
     * @throws TooManyAvoidedStretches
     * @throws RuntimeException when the router did not answer.
     */
    public function handle(AvoidStretchCommand $avoidStretchCommand): ?AvoidedStretch
    {
        $this->currentRider->assertSame($avoidStretchCommand->riderId);

        $roadEdge = $this->edgeFinder->near(
            $avoidStretchCommand->latitude,
            $avoidStretchCommand->longitude,
            $avoidStretchCommand->sport,
            $avoidStretchCommand->surface,
        );

        if (! $roadEdge instanceof RoadEdge) {
            return null;
        }

        $avoidedStretchId = $this->avoidedStretchRepository->add($avoidStretchCommand->riderId, new StretchToAvoid(
            latitude: $roadEdge->latitude,
            longitude: $roadEdge->longitude,
            shape: $roadEdge->shape,
            sport: $avoidStretchCommand->sport->value,
            surface: $avoidStretchCommand->surface->value,
            wayId: $roadEdge->wayId,
            name: $roadEdge->name,
        ), TooManyAvoidedStretches::MOST);

        return new AvoidedStretch(
            $avoidedStretchId->value,
            $roadEdge->latitude,
            $roadEdge->longitude,
            $roadEdge->shape,
            $roadEdge->name,
            $avoidStretchCommand->sport,
            $avoidStretchCommand->surface,
        );
    }
}
