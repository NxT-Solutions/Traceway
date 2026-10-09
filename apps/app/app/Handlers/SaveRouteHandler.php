<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\SaveRouteCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\SavedRouteRepository;
use App\Queries\Routes\SignedRouteLink;
use App\Routes\SavedRouteLink;

final readonly class SaveRouteHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private SavedRouteRepository $savedRouteRepository,
    ) {}

    public function handle(SaveRouteCommand $saveRouteCommand): SavedRouteLink
    {
        $this->currentRider->assertSame($saveRouteCommand->riderId);

        $id = $this->savedRouteRepository->store($saveRouteCommand);

        return new SavedRouteLink($id, SignedRouteLink::share($id));
    }
}
