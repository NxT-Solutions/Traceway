<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\PlaceNamer;
use Illuminate\Contracts\Cache\Repository;

/**
 * Asks about a point rounded to about a hundred metres, so the place search
 * never learns exactly where a pin was dropped, and remembers the name for
 * a month: names barely change, and the public place search asks every
 * client to keep what it was told. A point the map has no name for is asked
 * again next time.
 */
final readonly class CachedPlaceNamer implements PlaceNamer
{
    private const int SECONDS = 2_592_000;

    public function __construct(
        private PlaceNamer $placeNamer,
        private Repository $repository,
    ) {}

    public function nameAt(float $latitude, float $longitude): ?string
    {
        $latitude = round($latitude, 3);
        $longitude = round($longitude, 3);
        $key = sprintf('place-name:%.3f,%.3f', $latitude, $longitude);
        $cached = $this->repository->get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $name = $this->placeNamer->nameAt($latitude, $longitude);

        if ($name !== null) {
            $this->repository->put($key, $name, self::SECONDS);
        }

        return $name;
    }
}
