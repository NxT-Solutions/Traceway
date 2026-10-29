<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\MapServerBusy;
use App\Domain\Routing\MapServerPace;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Sleep;

/**
 * Keeps Traceway's share of a map server, and remembers a rest, in the cache
 * every rider's request shares.
 *
 * The share works like a bucket that empties at the server's pace: a
 * minute's worth of requests may go at once, and after that they go about as
 * fast as the bucket empties. A request that would overflow it waits a few
 * seconds for room, so a second rider planning at the same moment waits
 * rather than going to the backup. The cache holds when the bucket will be
 * empty. Two requests reading it at the same moment can both get in; at
 * Traceway's size that is one batch too many, now and then.
 */
final readonly class CachedMapServerPace implements MapServerPace
{
    public const int REST_SECONDS = 120;

    /** How long a request waits for its turn, when turns are kept, before the server counts as busy. */
    private const int TURN_WAIT_MILLISECONDS = 5_000;

    /** How long a request waits for room in the share before the server counts as busy. */
    private const int SHARE_WAIT_SECONDS = 8;

    private const int STEP_MILLISECONDS = 250;

    /**
     * @param  int  $perMinute  The requests a minute may hold, all at once or spread out. Zero is no limit, for a server of Traceway's own.
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

        $this->takeShare($requests);
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

    /**
     * Each request adds 60 / $perMinute seconds to the bucket, which empties
     * one second a second. A bucket fuller than a minute waits until it is
     * not; a wait longer than SHARE_WAIT_SECONDS is the server being busy,
     * and those requests are not counted.
     */
    private function takeShare(int $requests): void
    {
        $key = 'map-server:'.$this->server.':empty-at';
        $now = now()->getTimestampMs() / 1_000;
        $stored = $this->repository->get($key);
        $emptyAt = max(is_numeric($stored) ? (float) $stored : 0.0, $now) + $requests * 60 / $this->perMinute;
        $wait = $emptyAt - $now - 60;

        throw_if($wait > self::SHARE_WAIT_SECONDS, MapServerBusy::class, 'Traceway asked the map server for enough this minute.');

        $this->repository->put($key, $emptyAt, (int) ceil($emptyAt - $now) + 1);

        if ($wait > 0) {
            Sleep::for((int) ceil($wait * 1_000))->milliseconds();
        }
    }

    private function resting(): string
    {
        return 'map-server:'.$this->server.':resting';
    }
}
