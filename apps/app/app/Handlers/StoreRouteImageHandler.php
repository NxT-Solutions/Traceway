<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\StoreRouteImageCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\RouteImageStore;
use App\Contracts\Routes\SavedRouteRepository;

final readonly class StoreRouteImageHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private SavedRouteRepository $savedRouteRepository,
        private RouteImageStore $routeImageStore,
    ) {}

    public function handle(StoreRouteImageCommand $storeRouteImageCommand): void
    {
        $this->currentRider->assertSame($storeRouteImageCommand->riderId);

        // Only the rider who saved the route draws its picture. A 404 keeps
        // another rider's route id from leaking.
        abort_unless($this->savedRouteRepository->owns($storeRouteImageCommand->routeId, $storeRouteImageCommand->riderId), 404);

        $this->routeImageStore->put($storeRouteImageCommand->riderId, $storeRouteImageCommand->routeId, $storeRouteImageCommand->jpeg);
    }
}
