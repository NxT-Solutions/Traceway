<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionTokens;
use App\Commands\KeepConnectionAccessCommand;
use App\Contracts\Account\ConnectionRepository;
use App\Domain\Connections\ConnectionTally;
use App\Domain\Connections\GrantedAccesses;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;

/**
 * Refreshes and saves the keys of every connection to the service. Garmin Connect's refresh for about three
 * months only, and without fresh ones Traceway could not remove its access when the rider disconnects. Keys the
 * service turns down ask the rider to connect again. A service that is out of reach is tried on the next run.
 */
final readonly class KeepConnectionAccessHandler
{
    public function __construct(
        private ConnectionRepository $connectionRepository,
        private GrantedAccesses $grantedAccesses,
    ) {}

    public function handle(KeepConnectionAccessCommand $keepConnectionAccessCommand): ConnectionTally
    {
        $connectionProvider = $keepConnectionAccessCommand->connectionProvider;
        $slug = $connectionProvider->value;

        if (! $this->grantedAccesses->has($slug)) {
            return new ConnectionTally;
        }

        $done = 0;
        $refused = 0;
        $unreachable = 0;
        $skipped = 0;

        foreach ($this->connectionRepository->withKeys($connectionProvider) as $riderId) {
            $stored = $this->connectionRepository->tokens($riderId, $connectionProvider);

            if (! $stored instanceof ConnectionTokens) {
                $skipped++;

                continue;
            }

            try {
                $fresh = $this->grantedAccesses->refresh($slug, new ServiceTokens($stored->accessToken, $stored->refreshToken, $stored->expiresAt));
            } catch (ServiceRefused) {
                $this->connectionRepository->needsReconnect($riderId, $connectionProvider);
                $refused++;

                continue;
            } catch (ServiceUnavailable) {
                $unreachable++;

                continue;
            }

            $this->connectionRepository->refreshed($riderId, $connectionProvider, new ConnectionTokens($fresh->accessToken, $fresh->refreshToken, $fresh->expiresAt));
            $done++;
        }

        return new ConnectionTally($done, $refused, $unreachable, $skipped);
    }
}
