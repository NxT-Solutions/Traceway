<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionTokens;
use App\Account\RiderId;
use App\Commands\WithdrawConnectionAccessCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Asks the service to withdraw Traceway's access for an identity nobody kept. When a rider has connected that
 * account since, or may have, its access is theirs and stays. The service being down does not stop the rider.
 */
final readonly class WithdrawConnectionAccessHandler
{
    public function __construct(
        private ConnectionRepository $connectionRepository,
        private GrantedAccesses $grantedAccesses,
    ) {}

    public function handle(WithdrawConnectionAccessCommand $withdrawConnectionAccessCommand): void
    {
        $connectedIdentity = $withdrawConnectionAccessCommand->connectedIdentity;
        $connectionTokens = $connectedIdentity->tokens;

        if (! $connectionTokens instanceof ConnectionTokens) {
            return;
        }

        if ($this->connectionRepository->riderFor($connectedIdentity->provider, $connectedIdentity->providerUserId) instanceof RiderId) {
            return;
        }

        // A connection carried over from before has no number yet, so it could be this same account. A service
        // withdraws all of Traceway's access to an account at once, which would end that rider's too. One whose
        // keys the service turned down, or that is known to be another account, has nothing left to lose.
        if ($this->connectionRepository->unidentifiedWithLiveKeys($connectedIdentity->provider) !== []) {
            return;
        }

        try {
            $this->grantedAccesses->withdraw($connectedIdentity->provider->value, new ServiceTokens($connectionTokens->accessToken, $connectionTokens->refreshToken, $connectionTokens->expiresAt), now()->getTimestamp());
        } catch (ServiceRefused|ServiceUnavailable) {
            // Gone at the service already, or out of reach. Nothing here keeps the keys either way.
        }
    }
}
