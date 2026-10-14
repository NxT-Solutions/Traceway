<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim;

use App\Domain\Routing\PlaceKind;
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
     * The parts of an address a rider knows their home by, the town or
     * village before the city it belongs to, and before a hamlet or a
     * neighbourhood: "Hoboken", not "Nachtegalenhof".
     */
    private const array SETTLEMENT_PARTS = ['town', 'village', 'city', 'municipality', 'suburb', 'hamlet'];

    /**
     * The parts that place a town itself.
     */
    private const array REGION_PARTS = ['county', 'state'];

    /**
     * The parts of an address that name the street a house is on.
     */
    private const array STREET_PARTS = ['road', 'pedestrian', 'footway', 'cycleway', 'path', 'square', 'place'];

    /**
     * Address types that are a settlement or a region rather than a spot.
     */
    private const array TOWN_TYPES = ['city', 'town', 'village', 'hamlet', 'suburb', 'quarter', 'neighbourhood', 'municipality', 'county', 'state', 'region', 'province', 'country'];

    public function __construct(private NominatimConnector $nominatimConnector) {}

    /**
     * @return list<PlaceMatch>
     */
    public function search(string $query, float $nearLatitude, float $nearLongitude, string $language = 'en'): array
    {
        $response = $this->nominatimConnector->send(new SearchRequest($query, self::LIMIT, [
            max(-180.0, $nearLongitude - self::NEAR),
            max(-90.0, $nearLatitude - self::NEAR),
            min(180.0, $nearLongitude + self::NEAR),
            min(90.0, $nearLatitude + self::NEAR),
        ], $language));

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
        $address = is_array($result['address'] ?? null) ? $result['address'] : [];
        $displayName = is_string($result['display_name'] ?? null) ? $result['display_name'] : '';
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $displayName)), static fn (string $part): bool => $part !== ''));
        $street = $this->part($address, self::STREET_PARTS);
        $houseNumber = $this->part($address, ['house_number']);
        $ownName = is_string($result['name'] ?? null) ? trim($result['name']) : '';

        $name = match (true) {
            $street !== null && $houseNumber !== null => $street.' '.$houseNumber,
            $ownName !== '' => $ownName,
            $street !== null => $street,
            default => $parts[0] ?? '',
        };

        if ($name === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new PlaceMatch(
            $name,
            $this->area($address, $name, $street, $parts),
            (float) $latitude,
            (float) $longitude,
            $this->kind($result, $houseNumber !== null),
        );
    }

    /**
     * @param  array<mixed>  $result
     */
    private function kind(array $result, bool $hasHouseNumber): PlaceKind
    {
        $type = $result['addresstype'] ?? $result['type'] ?? null;

        return match (true) {
            $hasHouseNumber || in_array($type, ['house', 'building'], strict: true) => PlaceKind::Address,
            ($result['category'] ?? null) === 'highway' || $type === 'road' => PlaceKind::Street,
            in_array($type, self::TOWN_TYPES, strict: true) => PlaceKind::Town,
            default => PlaceKind::Place,
        };
    }

    /**
     * Where a match is: its town, or for a town its region, then the
     * country. "Hoboken, België" or "Turnhout, Belgium".
     *
     * @param  array<mixed>  $address
     * @param  list<string>  $parts
     */
    private function area(array $address, string $name, ?string $street, array $parts): string
    {
        $place = $this->part($address, self::SETTLEMENT_PARTS, $name)
            ?? $this->part($address, self::REGION_PARTS, $name);
        $country = $this->part($address, ['country'], $name);

        if ($place !== null || $country !== null) {
            return implode(', ', array_filter([$place, $country], static fn (?string $part): bool => $part !== null));
        }

        $rest = array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== $name && $part !== $street && ! is_numeric($part),
        ));

        return implode(', ', count($rest) > 2 ? [$rest[0], $rest[count($rest) - 1]] : $rest);
    }

    /**
     * The first of the named address parts that has a value, other than the
     * place's own name.
     *
     * @param  array<mixed>  $address
     * @param  list<string>  $keys
     */
    private function part(array $address, array $keys, ?string $except = null): ?string
    {
        foreach ($keys as $key) {
            $value = $address[$key] ?? null;

            if (is_string($value) && trim($value) !== '' && trim($value) !== $except) {
                return trim($value);
            }
        }

        return null;
    }
}
