<?php

declare(strict_types=1);

namespace App\Account;

use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;
use App\Contracts\Routes\RouteImageStore;
use App\Domain\Connections\GrantedAccesses;
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
        private ConnectionRepository $connectionRepository,
        private GrantedAccesses $grantedAccesses,
    ) {}

    public function close(RiderId $riderId): void
    {
        $this->withdrawConnections($riderId);
        $this->userRepository->endSessions($riderId);

        $rider = $this->userRepository->delete($riderId);
        $this->routeImageStore->forgetRider($riderId);
        $this->accountMailer->accountDeleted($rider->name, $rider->email);
    }

    /**
     * Withdraws Traceway's access at every service that gave it keys. One that cannot be reached does not stop
     * the deletion. A connection that keeps no keys goes with the account row.
     */
    private function withdrawConnections(RiderId $riderId): void
    {
        foreach ($this->connectionRepository->connected($riderId) as $connectionProvider) {
            $tokens = $this->connectionRepository->tokens($riderId, $connectionProvider);

            if (! $tokens instanceof ConnectionTokens) {
                continue;
            }

            try {
                $this->grantedAccesses->withdraw($connectionProvider->value, new ServiceTokens($tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt), now()->getTimestamp());
            } catch (ServiceRefused|ServiceUnavailable $exception) {
                // The report shows a service that may still hold Traceway's access.
                report($exception);
            }
        }
    }
}
