<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\RiderId;
use App\Commands\DisconnectConnectionCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Auth\IdentityProviders;
use App\Contracts\Mail\AccountMailer;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Asks the service to withdraw Traceway's access when Traceway holds keys, then forgets the connection and the
 * routes sent through it. The service being down does not keep anything here. A rider cannot disconnect their
 * last way to log in.
 */
final readonly class DisconnectConnectionHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ConnectionRepository $connectionRepository,
        private UserRepository $userRepository,
        private IdentityProviders $identityProviders,
        private GrantedAccesses $grantedAccesses,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(DisconnectConnectionCommand $disconnectConnectionCommand): void
    {
        $riderId = $disconnectConnectionCommand->riderId;
        $connectionProvider = $disconnectConnectionCommand->connectionProvider;

        $this->currentRider->assertSame($riderId);

        $connected = $this->connectionRepository->connected($riderId);

        if (! in_array($connectionProvider, $connected, strict: true)) {
            return;
        }

        if (! $this->keepsAWayIn($riderId, $connectionProvider, $connected)) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::LastSignInMethod);
        }

        $this->withdraw($connectionProvider, $this->connectionRepository->tokens($riderId, $connectionProvider));
        $this->connectionRepository->unlink($riderId, $connectionProvider);

        $rider = $this->userRepository->find($riderId);
        $this->accountMailer->connectionRemoved($rider->name, $rider->email, $connectionProvider);
    }

    /**
     * True when the rider can still log in without this connection: with a password, a passkey, or another
     * connection to a service that is set up here.
     *
     * @param  list<ConnectionProvider>  $connected
     */
    private function keepsAWayIn(RiderId $riderId, ConnectionProvider $connectionProvider, array $connected): bool
    {
        if ($this->userRepository->hasPassword($riderId) || $this->userRepository->passkeyCount($riderId) > 0) {
            return true;
        }

        return array_any($connected, fn (ConnectionProvider $other): bool => $other !== $connectionProvider && $this->identityProviders->isEnabled($other));
    }

    private function withdraw(ConnectionProvider $connectionProvider, ?ConnectionTokens $connectionTokens): void
    {
        if (! $connectionTokens instanceof ConnectionTokens) {
            return;
        }

        try {
            $this->grantedAccesses->withdraw($connectionProvider->value, new ServiceTokens($connectionTokens->accessToken, $connectionTokens->refreshToken, $connectionTokens->expiresAt), now()->getTimestamp());
        } catch (ServiceRefused|ServiceUnavailable $exception) {
            // Gone at the service already, or out of reach. The rider is disconnected here either way, and the
            // report shows a service that may still hold Traceway's access.
            report($exception);
        }
    }
}
