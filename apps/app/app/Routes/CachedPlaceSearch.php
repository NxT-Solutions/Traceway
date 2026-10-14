<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\PlaceKind;
use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * Searches with the map's centre rounded to about ten kilometres, so the
 * place search never learns where a rider is looking, and remembers the
 * answer for a month, as the public place search asks. "Westerlo" and
 * " westerlo " are one search. A search waits up to five seconds for
 * Traceway's turn; one that gets no turn is busy.
 */
final readonly class CachedPlaceSearch implements PlaceSearch
{
    private const int SECONDS = 2_592_000;

    /**
     * A rider who pressed Enter waits a little longer for a turn than a pin
     * waits for its name: the turn is shared with every pin on Traceway.
     */
    private const int WAIT_MILLISECONDS = 5000;

    public function __construct(
        private PlaceSearch $placeSearch,
        private Repository $repository,
        private PlaceSearchPace $placeSearchPace,
    ) {}

    /**
     * @throws PlaceSearchBusy when no turn came
     */
    public function search(string $query, float $nearLatitude, float $nearLongitude): array
    {
        $query = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($query)));
        $nearLatitude = round($nearLatitude, 1);
        $nearLongitude = round($nearLongitude, 1);
        // The version changes whenever a match is named differently, so old
        // answers are not shown for a month.
        $key = sprintf('place-search:v3:%.1f,%.1f:%s', $nearLatitude, $nearLongitude, hash('xxh128', $query));
        $cached = $this->repository->get($key);

        if (is_array($cached)) {
            return $this->matches($cached);
        }

        throw_unless($this->placeSearchPace->take(self::WAIT_MILLISECONDS), PlaceSearchBusy::noTurn());

        try {
            $matches = $this->placeSearch->search($query, $nearLatitude, $nearLongitude);
        } catch (Throwable $throwable) {
            $this->placeSearchPace->rest();

            throw $throwable;
        }

        $this->repository->put($key, array_map(static fn (PlaceMatch $placeMatch): array => [
            'name' => $placeMatch->name,
            'area' => $placeMatch->area,
            'latitude' => $placeMatch->latitude,
            'longitude' => $placeMatch->longitude,
            'kind' => $placeMatch->kind->value,
        ], $matches), self::SECONDS);

        return $matches;
    }

    /**
     * @param  array<mixed>  $cached
     * @return list<PlaceMatch>
     */
    private function matches(array $cached): array
    {
        $matches = [];

        foreach ($cached as $row) {
            if (is_array($row) && is_string($row['name'] ?? null) && is_string($row['area'] ?? null)
                && is_float($row['latitude'] ?? null) && is_float($row['longitude'] ?? null)) {
                $kind = is_string($row['kind'] ?? null) ? PlaceKind::tryFrom($row['kind']) : null;
                $matches[] = new PlaceMatch($row['name'], $row['area'], $row['latitude'], $row['longitude'], $kind ?? PlaceKind::Place);
            }
        }

        return $matches;
    }
}
