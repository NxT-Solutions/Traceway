<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim;

use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use App\Integrations\Nominatim\Requests\SearchRequest;
use RuntimeException;

final readonly class NominatimPlaceSearch implements PlaceSearch
{
    /**
     * Enough to tell the Oevels apart, few enough to read on a phone.
     */
    private const int LIMIT = 5;

    /**
     * Half the side of the box, in degrees, where places rank first: about
     * fifty kilometres around the map.
     */
    private const float NEAR = 0.5;

    /**
     * The parts of an address that place a match, the smallest first.
     */
    private const array AREA_PARTS = ['village', 'town', 'city', 'municipality', 'county', 'state', 'country'];

    public function __construct(private NominatimConnector $nominatimConnector) {}

    /**
     * @return list<PlaceMatch>
     */
    public function search(string $query, float $nearLatitude, float $nearLongitude): array
    {
        $response = $this->nominatimConnector->send(new SearchRequest($query, self::LIMIT, [
            max(-180.0, $nearLongitude - self::NEAR),
            max(-90.0, $nearLatitude - self::NEAR),
            min(180.0, $nearLongitude + self::NEAR),
            min(90.0, $nearLatitude + self::NEAR),
        ]));

        throw_if($response->failed(), RuntimeException::class, 'The place search did not answer.');

        $results = $response->json();
        $matches = [];

        foreach ($results as $result) {
            $match = is_array($result) ? $this->match($result) : null;

            if ($match instanceof PlaceMatch) {
                $matches[] = $match;
            }
        }

        return $matches;
    }

    /**
     * @param  array<mixed>  $result
     */
    private function match(array $result): ?PlaceMatch
    {
        $latitude = $result['lat'] ?? null;
        $longitude = $result['lon'] ?? null;
        $displayName = is_string($result['display_name'] ?? null) ? $result['display_name'] : '';
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $displayName)), static fn (string $part): bool => $part !== ''));
        $name = is_string($result['name'] ?? null) && $result['name'] !== '' ? $result['name'] : ($parts[0] ?? '');

        if ($name === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new PlaceMatch($name, $this->area($result, $name, $parts), (float) $latitude, (float) $longitude);
    }

    /**
     * The two smallest parts of the address that are not the place itself,
     * like "Westerlo, Belgium" or "Antwerp, Belgium".
     *
     * @param  array<mixed>  $result
     * @param  list<string>  $parts
     */
    private function area(array $result, string $name, array $parts): string
    {
        $address = is_array($result['address'] ?? null) ? $result['address'] : [];
        $area = [];

        foreach (self::AREA_PARTS as $part) {
            $value = $address[$part] ?? null;

            if (is_string($value) && $value !== '' && $value !== $name && ! in_array($value, $area, strict: true)) {
                $area[] = $value;
            }
        }

        if ($area === []) {
            $area = array_values(array_filter($parts, static fn (string $part): bool => $part !== $name));
        }

        return implode(', ', count($area) > 2 ? [$area[0], $area[count($area) - 1]] : $area);
    }
}
