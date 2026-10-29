<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\MapServerPace;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Sleep;

/**
 * Counts the requests of each minute for all of Traceway, and remembers a
 * rest, in the cache every rider's request shares.
 */
final readonly class CachedMapServerPace implements MapServerPace
{
    public const int REST_SECONDS = 120;

    /** How long a request waits for its turn, when turns are kept, before the server counts as busy. */
    private const int TURN_WAIT_MILLISECONDS = 5_000;

    private const int STEP_MILLISECONDS = 250;

    /**
     * @param  int  $perMinute  The requests one minute may hold. Zero is no limit, for a server of Traceway's own.
     * @param  string  $server  Which server the share is for. Each keeps its own count and its own rest.
     * @param  bool  $oneASecond  Requests also take turns, about one a second, for a server that asks for that
     *                            and is asked one request at a time.
     */
    public function __construct(
        private Repository $repository,
        private int $perMinute = 60,
        private string $server = 'valhalla',
        private bool $oneASecond = false,
    ) {}

    public function admit(int $requests): void
    {
        throw_if($this->repository->has($this->resting()), MapServerBusy::class, 'Traceway is giving the map server a rest.');

        if ($this->perMinute <= 0 || $requests <= 0) {
            return;
        }

        if ($this->oneASecond) {
            $this->takeTurn();
        }

        $minute = 'map-server:'.$this->server.':sent:'.intdiv(now()->getTimestamp(), 60);
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

        $this->repository->put($this->resting(), value: true, ttl: self::REST_SECONDS);
    }

    /**
     * The cache counts whole seconds, so a turn kept for one second lasts
     * up to one: about one request a second, for all of Traceway.
     */
    private function takeTurn(): void
    {
        for ($waited = 0; ! $this->repository->add('map-server:'.$this->server.':turn', value: true, ttl: 1); $waited += self::STEP_MILLISECONDS) {
            throw_if($waited >= self::TURN_WAIT_MILLISECONDS, MapServerBusy::class, 'Traceway waited too long for its turn at the map server.');

            Sleep::for(self::STEP_MILLISECONDS)->milliseconds();
        }
    }

    private function resting(): string
    {
        return 'map-server:'.$this->server.':resting';
    }
}
