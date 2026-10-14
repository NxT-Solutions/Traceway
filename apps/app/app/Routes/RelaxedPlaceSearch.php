<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\PlaceSearch;

/**
 * The public place search finds nothing for one wrong word. When a search of
 * a few words finds nothing, it asks once more without the last word, so
 * "Roodborstlaan 3, Hobokne" still finds the street. The retry waits for its
 * own turn like any search, and finds nothing when it gets none.
 */
final readonly class RelaxedPlaceSearch implements PlaceSearch
{
    public function __construct(private PlaceSearch $placeSearch) {}

    public function search(string $query, float $nearLatitude, float $nearLongitude): array
    {
        $matches = $this->placeSearch->search($query, $nearLatitude, $nearLongitude);

        if ($matches !== []) {
            return $matches;
        }

        $words = preg_split('/[\s,]+/u', trim($query), flags: PREG_SPLIT_NO_EMPTY);

        if (! is_array($words) || count($words) < 2) {
            return [];
        }

        $shorter = implode(' ', array_slice($words, 0, -1));

        if (mb_strlen($shorter) < 2) {
            return [];
        }

        // The first answer stands when the retry gets no turn: nothing found.
        try {
            return $this->placeSearch->search($shorter, $nearLatitude, $nearLongitude);
        } catch (PlaceSearchBusy) {
            return [];
        }
    }
}
