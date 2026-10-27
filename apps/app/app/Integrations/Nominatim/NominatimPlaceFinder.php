<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim;

use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use App\Integrations\JsonBody;
use App\Integrations\Nominatim\Requests\SearchRequest;
use RuntimeException;

final readonly class NominatimPlaceFinder implements PlaceFinder
{
    public function __construct(private NominatimConnector $nominatimConnector) {}

    public function find(string $search): ?Place
    {
        $response = $this->nominatimConnector->send(new SearchRequest($search));

        throw_if($response->failed() || ! JsonBody::readable($response), RuntimeException::class, 'The place search did not answer.');

        $match = $response->json(0);

        if (! is_array($match)) {
            return null;
        }

        $latitude = $match['lat'] ?? null;
        $longitude = $match['lon'] ?? null;
        $name = $match['display_name'] ?? $search;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new Place(
            is_string($name) ? $name : $search,
            (float) $latitude,
            (float) $longitude,
        );
    }
}
