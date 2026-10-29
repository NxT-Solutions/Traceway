<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\MapServerPace;
use Illuminate\Contracts\Cache\Repository;

/**
 * Counts the requests of each minute for all of Traceway, and remembers a
 * rest, in the cache every rider's request shares.
 */
final readonly class CachedMapServerPace implements MapServerPace
{
    private const string SENT = 'map-server:sent:';

    private const string RESTING = 'map-server:resting';

    public const int REST_SECONDS = 120;

    /**
     * @param  int  $perMinute  The requests one minute may hold. Zero is no limit, for a server of Traceway's own.
     */
    public function __construct(
        private Repository $repository,
        private int $perMinute = 60,
    ) {}

    public function admit(int $requests): void
    {
        throw_if($this->repository->has(self::RESTING), MapServerBusy::class, 'Traceway is giving the map server a rest.');

        if ($this->perMinute <= 0 || $requests <= 0) {
            return;
        }

        $minute = self::SENT.intdiv(now()->getTimestamp(), 60);
        $this->repository->add($minute, value: 0, ttl: 120);
        $sent = $this->repository->increment($minute, $requests);

        // Requests that do not fit are not sent, so they give their share back.
        if (! is_int($sent) || $sent > $this->perMinute) {
            $this->repository->decrement($minute, $requests);

            throw new MapServerBusy('Traceway asked the map server for enough this minute.');
        }
    }

    /**
     * A server of Traceway's own blocks nobody, so it gets no rest.
     */
    public function rest(): void
    {
        if ($this->perMinute <= 0) {
            return;
        }

        $this->repository->put(self::RESTING, value: true, ttl: self::REST_SECONDS);
    }
}
