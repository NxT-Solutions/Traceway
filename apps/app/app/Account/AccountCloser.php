<?php

declare(strict_types=1);

namespace App\Account;

use App\Contracts\Account\RouteServiceRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\RouteImageStore;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Closes an account the same way whether the rider deletes it or an admin does: Traceway's access at every
 * connected service is withdrawn, the account and its pictures go, and the rider gets one last email.
 */
final readonly class AccountCloser
{
    public function __construct(
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
        private RouteImageStore $routeImageStore,
        private RouteServiceRepository $routeServiceRepository,
        private CourseServices $courseServices,
    ) {}

    public function close(RiderId $riderId): void
    {
        $this->revokeRouteServices($riderId);
        $this->userRepository->endSessions($riderId);

        $rider = $this->userRepository->delete($riderId);
        $this->routeImageStore->forgetRider($riderId);
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
                $this->courseServices->withdraw($routeService->value, new ServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt), now()->getTimestamp());
            } catch (ServiceRefused|ServiceUnavailable) {
                continue;
            }
        }
    }
}
