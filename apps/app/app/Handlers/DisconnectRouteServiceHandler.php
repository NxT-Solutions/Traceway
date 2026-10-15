<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\RouteServiceTokens;
use App\Commands\DisconnectRouteServiceCommand;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Auth\CurrentRider;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

final readonly class DisconnectRouteServiceHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private CourseServices $courseServices,
        private RouteServiceRepository $routeServiceRepository,
    ) {}

    /**
     * Asks the service to withdraw Traceway's access, then forgets the tokens. The service being down does not
     * keep the tokens here.
     */
    public function handle(DisconnectRouteServiceCommand $disconnectRouteServiceCommand): void
    {
        $this->currentRider->assertSame($disconnectRouteServiceCommand->riderId);

        $service = $disconnectRouteServiceCommand->routeService;
        $tokens = $this->routeServiceRepository->tokens($disconnectRouteServiceCommand->riderId, $service);

        if ($tokens instanceof RouteServiceTokens && $this->courseServices->has($service->value)) {
            try {
                $this->courseServices->withdraw($service->value, new ServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt), now()->getTimestamp());
            } catch (ServiceRefused|ServiceUnavailable) {
                // Gone at the service already, or out of reach. The rider is disconnected here either way.
            }
        }

        $this->routeServiceRepository->disconnect($disconnectRouteServiceCommand->riderId, $service);
    }
}
