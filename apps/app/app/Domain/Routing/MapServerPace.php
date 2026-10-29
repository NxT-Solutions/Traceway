<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * How much Traceway asks of the map server. The public one allows about one
 * request a second, and stops answering a client that asks for much more,
 * for an hour and for every rider. Every request to it takes its share here
 * first, whichever rider asks.
 */
interface MapServerPace
{
    /**
     * Takes a share for this many requests.
     *
     * @throws MapServerBusy while Traceway rests, or when this minute's share is used up.
     */
    public function admit(int $requests): void;

    /**
     * Stops every request for a while after the server turned Traceway away
     * or did not answer, so Traceway does not keep knocking.
     */
    public function rest(): void;
}
