<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Commands\ClaimUnconfirmedAccountCommand;
use App\Contracts\Account\ConnectedAppRepository;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\DeviceLinkRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Whoever opened an account with an email nobody confirmed may have added their own ways in. When the owner of
 * the address proves it with a reset link, every connection, passkey, two-factor setup, paired device, AI
 * assistant, and session goes, so the password they set is the only way in. Keys a service gave are handed back
 * where Traceway can.
 */
final readonly class ClaimUnconfirmedAccountHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ConnectionRepository $connectionRepository,
        private UserRepository $userRepository,
        private GrantedAccesses $grantedAccesses,
        private ConnectedAppRepository $connectedAppRepository,
        private DeviceLinkRepository $deviceLinkRepository,
    ) {}

    public function handle(ClaimUnconfirmedAccountCommand $claimUnconfirmedAccountCommand): void
    {
        $riderId = $claimUnconfirmedAccountCommand->riderId;

        $this->currentRider->assertSameOrGuest($riderId);

        foreach ($this->connectionRepository->connected($riderId) as $connectionProvider) {
            $this->withdraw($connectionProvider, $this->connectionRepository->tokens($riderId, $connectionProvider));
            $this->connectionRepository->unlink($riderId, $connectionProvider);
        }

        $this->userRepository->deletePasskeys($riderId);
        $this->connectedAppRepository->disconnectAll($riderId);
        $this->deviceLinkRepository->revokeAll($riderId);
        $this->userRepository->resetTwoFactor($riderId);
        $this->userRepository->endSessions($riderId);
    }

    private function withdraw(ConnectionProvider $connectionProvider, ?ConnectionTokens $connectionTokens): void
    {
        if (! $connectionTokens instanceof ConnectionTokens) {
            return;
        }

        try {
            $this->grantedAccesses->withdraw($connectionProvider->value, new ServiceTokens($connectionTokens->accessToken, $connectionTokens->refreshToken, $connectionTokens->expiresAt), now()->getTimestamp());
        } catch (ServiceRefused|ServiceUnavailable) {
            // Gone at the service already, or out of reach. The connection goes here either way.
        }
    }
}
