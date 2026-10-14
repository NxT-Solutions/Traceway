<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use Throwable;

/**
 * An AI tool looks up the start of a loop through the same place search as
 * the planner's pins, so it waits for Traceway's turn. It asks even when no
 * turn came, because a plan cannot start without its place.
 */
final readonly class PacedPlaceFinder implements PlaceFinder
{
    private const int WAIT_MILLISECONDS = 2000;

    public function __construct(
        private PlaceFinder $placeFinder,
        private PlaceSearchPace $placeSearchPace,
    ) {}

    public function find(string $search): ?Place
    {
        $this->placeSearchPace->take(self::WAIT_MILLISECONDS);

        try {
            return $this->placeFinder->find($search);
        } catch (Throwable $throwable) {
            $this->placeSearchPace->rest();

            throw $throwable;
        }
    }
}
