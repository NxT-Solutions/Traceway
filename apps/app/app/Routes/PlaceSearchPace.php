<?php

declare(strict_types=1);

namespace App\Routes;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Sleep;

/**
 * The public place search allows one request a second from all of
 * Traceway, and asks a client it turned away to stop for a while. Every
 * lookup takes its turn here first, whichever rider or tool asks.
 */
final readonly class PlaceSearchPace
{
    private const string TURN = 'place-search:turn';

    private const string RESTING = 'place-search:resting';

    private const int REST_SECONDS = 60;

    private const int STEP_MILLISECONDS = 200;

    public function __construct(private Repository $repository) {}

    /**
     * Takes a turn, waiting up to the given time for one. False when no turn
     * came, or while Traceway rests after the search turned it away.
     */
    public function take(int $waitMilliseconds = 0): bool
    {
        for ($waited = 0; ; $waited += self::STEP_MILLISECONDS) {
            if ($this->repository->has(self::RESTING)) {
                return false;
            }

            if ($this->repository->add(self::TURN, value: true, ttl: 1)) {
                return true;
            }

            if ($waited >= $waitMilliseconds) {
                return false;
            }

            Sleep::for(self::STEP_MILLISECONDS)->milliseconds();
        }
    }

    /**
     * Stops every lookup for a minute after the search failed or refused.
     */
    public function rest(): void
    {
        $this->repository->put(self::RESTING, value: true, ttl: self::REST_SECONDS);
    }
}
