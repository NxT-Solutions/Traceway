<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\RouteServiceTokens;
use App\Commands\ConnectRouteServiceCommand;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Auth\CurrentRider;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceUnavailable;

final readonly class ConnectRouteServiceHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private CourseServices $courseServices,
        private RouteServiceRepository $routeServiceRepository,
    ) {}

    public function handle(ConnectRouteServiceCommand $connectRouteServiceCommand): void
    {
        $this->currentRider->assertSame($connectRouteServiceCommand->riderId);

        $service = $connectRouteServiceCommand->routeService;

        abort_unless($this->courseServices->has($service->value), 404);

        try {
            $tokens = $this->courseServices->for($service->value)->connect($connectRouteServiceCommand->code);
        } catch (ServiceRefused) {
            abort(422, $service->label().' did not connect. Try again.');
        } catch (ServiceUnavailable) {
            abort(502, $service->label().' could not be reached. Try again in a moment.');
        }

        $this->routeServiceRepository->connect(
            $connectRouteServiceCommand->riderId,
            $service,
            new RouteServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt),
        );
    }
}
