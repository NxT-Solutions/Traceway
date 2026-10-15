<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\RiderId;
use App\Account\RouteServiceTokens;
use App\Commands\DeleteAccountCommand;
use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\RouteImageStore;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

final readonly class DeleteAccountHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
        private RouteImageStore $routeImageStore,
        private RouteServiceRepository $routeServiceRepository,
        private CourseServices $courseServices,
    ) {}

    public function handle(DeleteAccountCommand $deleteAccountCommand): void
    {
        $this->currentRider->assertSame($deleteAccountCommand->riderId);

        $this->revokeRouteServices($deleteAccountCommand->riderId);

        $rider = $this->userRepository->delete($deleteAccountCommand->riderId);
        $this->routeImageStore->forgetRider($deleteAccountCommand->riderId);
        $this->accountMailer->accountDeleted($rider->name, $rider->email);
    }

    /**
     * Withdraws Traceway's access at every service the rider connected. One that cannot be reached does not stop
     * the deletion.
     */
    private function revokeRouteServices(RiderId $riderId): void
    {
        foreach ($this->routeServiceRepository->connected($riderId) as $routeService) {
            $tokens = $this->routeServiceRepository->tokens($riderId, $routeService);

            if (! $tokens instanceof RouteServiceTokens || ! $this->courseServices->has($routeService->value)) {
                continue;
            }

            try {
                $this->courseServices->for($routeService->value)->revoke(new ServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt));
            } catch (ServiceRefused|ServiceUnavailable) {
                continue;
            }
        }
    }
}
