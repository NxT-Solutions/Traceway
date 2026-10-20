<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionFailed;
use App\Account\ConnectionProblem;
use App\Account\ConnectionTokens;
use App\Account\LinkOutcome;
use App\Account\RiderId;
use App\Commands\LinkConnectionCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Auth\CurrentRider;
use App\Contracts\Mail\AccountMailer;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Connects the account and tells the rider by email when it adds a new way to log in, so a connection they did
 * not make does not go unnoticed. An account at a service belongs to one Traceway account only.
 */
final readonly class LinkConnectionHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ConnectionRepository $connectionRepository,
        private UserRepository $userRepository,
        private GrantedAccesses $grantedAccesses,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(LinkConnectionCommand $linkConnectionCommand): LinkOutcome
    {
        $riderId = $linkConnectionCommand->riderId;
        $connectedIdentity = $linkConnectionCommand->connectedIdentity;
        $connectionProvider = $connectedIdentity->provider;

        $this->currentRider->assertSame($riderId);

        $owner = $this->connectionRepository->riderFor($connectionProvider, $connectedIdentity->providerUserId);

        if ($owner instanceof RiderId && $owner->value !== $riderId->value) {
            throw ConnectionFailed::because($connectionProvider, ConnectionProblem::TakenByAnotherRider);
        }

        $previous = $this->connectionRepository->identityOf($riderId, $connectionProvider);
        $stored = $this->connectionRepository->tokens($riderId, $connectionProvider);

        if ($previous !== null && $previous !== $connectedIdentity->providerUserId) {
            $this->withdraw($connectionProvider->value, $stored);
        }

        $linkOutcome = $this->connectionRepository->link($riderId, $connectedIdentity);

        if ($linkOutcome->supersedesKeys()) {
            $this->supersede($connectionProvider->value, $stored, $connectedIdentity->tokens);
        }

        if ($linkOutcome->addsSignIn()) {
            $rider = $this->userRepository->find($riderId);
            $this->accountMailer->connectionAdded($rider->name, $rider->email, $connectionProvider);
        }

        return $linkOutcome;
    }

    /**
     * Hands back the keys of the account that is being replaced. A service that is down does not stop the swap.
     */
    private function withdraw(string $slug, ?ConnectionTokens $connectionTokens): void
    {
        if (! $connectionTokens instanceof ConnectionTokens) {
            return;
        }

        try {
            $this->grantedAccesses->withdraw($slug, new ServiceTokens($connectionTokens->accessToken, $connectionTokens->refreshToken, $connectionTokens->expiresAt), now()->getTimestamp());
        } catch (ServiceRefused|ServiceUnavailable) {
            // Gone at the service already, or out of reach. The old keys are forgotten here either way.
        }
    }

    /**
     * Lets go of the keys the new ones replaced, where the service can end one key alone. The rider stays
     * connected whatever the service answers.
     */
    private function supersede(string $slug, ?ConnectionTokens $old, ?ConnectionTokens $new): void
    {
        if (! $old instanceof ConnectionTokens || ! $new instanceof ConnectionTokens) {
            return;
        }

        try {
            $this->grantedAccesses->supersede(
                $slug,
                new ServiceTokens($old->accessToken, $old->refreshToken, $old->expiresAt),
                new ServiceTokens($new->accessToken, $new->refreshToken, $new->expiresAt),
            );
        } catch (ServiceRefused|ServiceUnavailable) {
            // The old key stopped working already, or the service is out of reach.
        }
    }
}
