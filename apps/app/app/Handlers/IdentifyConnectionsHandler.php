<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Account\ConnectionProvider;
use App\Account\ConnectionTokens;
use App\Account\RiderId;
use App\Account\RouteService;
use App\Contracts\Account\ConnectionRepository;
use App\Contracts\Account\UserRepository;
use App\Contracts\Mail\AccountMailer;
use App\Domain\Connections\ConnectionTally;
use App\Domain\Courses\Services\CourseService;
use App\Domain\Courses\Services\CourseServices;
use App\Domain\Courses\Services\ServiceAccount;
use App\Domain\Courses\Services\ServiceRefused;
use App\Domain\Courses\Services\ServiceTokens;
use App\Domain\Courses\Services\ServiceUnavailable;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

/**
 * Gives each Wahoo and Ride with GPS connection carried over from before the service's number for its account, so
 * it logs the rider in, and emails the rider that it does, as for any new way in. Only connections with keys the
 * service has not turned down are asked. Keys the service turns down are forgotten, and the rider connects again.
 * A service that cannot answer is left alone until the next run. An account that another rider connected already
 * is reported once, because it cannot belong to two riders.
 */
final readonly class IdentifyConnectionsHandler
{
    /** Failures in a row that mean the service is down, not one rider's account. */
    private const int OUTAGE = 3;

    public function __construct(
        private ConnectionRepository $connectionRepository,
        private CourseServices $courseServices,
        private Repository $repository,
        private UserRepository $userRepository,
        private AccountMailer $accountMailer,
    ) {}

    public function handle(): ConnectionTally
    {
        $done = 0;
        $refused = 0;
        $unreachable = 0;
        $skipped = 0;

        foreach (RouteService::cases() as $routeService) {
            if (! $this->courseServices->has($routeService->value)) {
                continue;
            }

            $connectionProvider = $routeService->connection();
            $courseService = $this->courseServices->for($routeService->value);
            $riders = $this->connectionRepository->unidentifiedWithLiveKeys($connectionProvider);
            $failedInARow = 0;

            foreach ($riders as $index => $riderId) {
                try {
                    $serviceAccount = $this->account($courseService, $riderId, $connectionProvider);
                } catch (ServiceRefused) {
                    // The keys are dead, so they could not end anyone's access either.
                    $this->connectionRepository->forgetKeys($riderId, $connectionProvider);
                    $refused++;
                    $failedInARow = 0;

                    continue;
                } catch (ServiceUnavailable) {
                    // One rider's account can fail on its own, so the others are still asked. Several in a row is
                    // the service being down or slow, which would hold up every connection after them too, so
                    // the rest wait for the next run.
                    $unreachable++;

                    if (++$failedInARow >= self::OUTAGE) {
                        $unreachable += count($riders) - $index - 1;

                        break;
                    }

                    continue;
                }

                $failedInARow = 0;

                $owner = $this->connectionRepository->riderFor($connectionProvider, $serviceAccount->id);

                if ($owner instanceof RiderId) {
                    $this->reportTaken($connectionProvider, $riderId, $owner);

                    // The connection is another rider's account, so it cannot log this rider in, and it is not
                    // the account of a sign-up nobody finished. Marking it for connecting again keeps it out of
                    // the next run, and it no longer stops Traceway handing back such a sign-up's keys. Its keys
                    // stay, so routes still go through it.
                    $this->connectionRepository->needsReconnect($riderId, $connectionProvider);
                    $skipped++;

                    continue;
                }

                $this->connectionRepository->identify($riderId, $connectionProvider, $serviceAccount->id, $serviceAccount->email);
                $rider = $this->userRepository->find($riderId);
                $this->accountMailer->connectionAdded($rider->name, $rider->email, $connectionProvider);
                $done++;
            }
        }

        return new ConnectionTally($done, $refused, $unreachable, $skipped);
    }

    /**
     * Asks the service whose keys these are, refreshing them first when they ran out.
     *
     * @throws ServiceRefused when the rider has to connect again
     * @throws ServiceUnavailable when the service cannot answer now
     */
    private function account(CourseService $courseService, RiderId $riderId, ConnectionProvider $connectionProvider): ServiceAccount
    {
        $stored = $this->connectionRepository->tokens($riderId, $connectionProvider);

        throw_unless($stored instanceof ConnectionTokens, ServiceRefused::class, 'The connection keeps no keys to ask with.');

        $serviceTokens = new ServiceTokens($stored->accessToken, $stored->refreshToken, $stored->expiresAt);

        if ($serviceTokens->expiresBy(now()->getTimestamp())) {
            $serviceTokens = $courseService->refresh($serviceTokens);
            $this->connectionRepository->refreshed($riderId, $connectionProvider, new ConnectionTokens($serviceTokens->accessToken, $serviceTokens->refreshToken, $serviceTokens->expiresAt));
        }

        return $courseService->account($serviceTokens);
    }

    /**
     * Tells whoever runs Traceway once, not on every run: the connection comes back to a run when its keys are
     * refreshed, such as when the rider sends a route.
     */
    private function reportTaken(ConnectionProvider $connectionProvider, RiderId $riderId, RiderId $owner): void
    {
        $key = 'connections.identify.taken.'.$connectionProvider->value.'.'.$riderId->value.'.'.$owner->value;

        if (! $this->repository->add($key, value: true, ttl: now()->addYear())) {
            return;
        }

        report(new RuntimeException('The '.$connectionProvider->label().' account of rider '.$riderId->value.' is connected to rider '.$owner->value.' already, so it was left without a number.'));
    }
}
