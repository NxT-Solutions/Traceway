<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\AvoidStretchCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\AvoidedStretchRepository;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\EdgeFinder;
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

        throw_if(
            $this->avoidedStretchRepository->countFor($avoidStretchCommand->riderId) >= TooManyAvoidedStretches::MOST,
            TooManyAvoidedStretches::limit(),
        );

        $roadEdge = $this->edgeFinder->near(
            $avoidStretchCommand->latitude,
            $avoidStretchCommand->longitude,
            $avoidStretchCommand->sport,
            $avoidStretchCommand->surface,
        );

        if ($roadEdge === null) {
            return null;
        }

        return $this->avoidedStretchRepository->add($avoidStretchCommand->riderId, $roadEdge);
    }
}
