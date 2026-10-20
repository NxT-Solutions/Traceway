<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionTokens;
use App\Commands\RecordConnectionSignInCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Auth\CurrentRider;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Runs while the rider is still a guest, before a two-factor challenge, once the service has said this is their
 * account. The controller only sends it for the rider that account belongs to. A service that gave new keys has
 * the old ones let go where it can end one key alone.
 */
final readonly class RecordConnectionSignInHandler
{
    public function __construct(
        private CurrentRider $currentRider,
        private ConnectionRepository $connectionRepository,
        private GrantedAccesses $grantedAccesses,
    ) {}

    public function handle(RecordConnectionSignInCommand $recordConnectionSignInCommand): void
    {
        $riderId = $recordConnectionSignInCommand->riderId;
        $connectedIdentity = $recordConnectionSignInCommand->connectedIdentity;
        $connectionProvider = $connectedIdentity->provider;

        $this->currentRider->assertSameOrGuest($riderId);

        $stored = $this->connectionRepository->tokens($riderId, $connectionProvider);
        $linkOutcome = $this->connectionRepository->link($riderId, $connectedIdentity);
        $this->connectionRepository->signedIn($riderId, $connectionProvider, now()->toDateTimeImmutable());

        if ($linkOutcome->supersedesKeys()) {
            $this->supersede($connectionProvider->value, $stored, $connectedIdentity->tokens);
        }
    }

    /**
     * The log in goes on whatever the service answers.
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
