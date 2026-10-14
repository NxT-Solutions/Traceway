<?php

declare(strict_types=1);

namespace App\Routes;

use RuntimeException;

/**
 * The place search had no turn for a rider's search, or turned Traceway
 * away a moment ago. Searching again in a little while works.
 */
final class PlaceSearchBusy extends RuntimeException
{
    public static function noTurn(): self
    {
        return new self('The place search is busy.');
    }
}
