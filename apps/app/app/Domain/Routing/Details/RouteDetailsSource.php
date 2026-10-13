<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

use App\Domain\Routing\Sport;

/**
 * The details of a finished line: climb, surfaces, turns, and stops.
 */
interface RouteDetailsSource
{
    /**
     * Everything about the line. Stops are left out when the stop finder
     * does not answer.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    public function for(Sport $sport, array $line): RouteDetails;

    /**
     * The climb, the surfaces, and the turns, without stops.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    public function road(Sport $sport, array $line): RouteDetails;

    /**
     * Water, toilets, and cafés on the line. Throws when the stop finder
     * does not answer, so an empty list means there are none.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<Stop>
     */
    public function stops(Sport $sport, array $line): array;
}
