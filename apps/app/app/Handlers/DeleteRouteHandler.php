<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Commands\DeleteRouteCommand;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Routes\SavedRouteRepository;
use App\Routes\ForeignRoute;
use App\Routes\MissingRoute;

final readonly class DeleteRouteHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private SavedRouteRepository $savedRouteRepository,
    ) {}

    public function handle(DeleteRouteCommand $deleteRouteCommand): void
    {
        $this->currentRider->assertSame($deleteRouteCommand->riderId);

        try {
            $this->savedRouteRepository->deleteOwned($deleteRouteCommand->routeId, $deleteRouteCommand->riderId);
        } catch (MissingRoute) {
            abort(404);
        } catch (ForeignRoute) {
            abort(403);
        }
    }
}
