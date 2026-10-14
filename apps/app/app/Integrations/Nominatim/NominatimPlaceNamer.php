<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim;

use App\Domain\Routing\PlaceNamer;
use App\Integrations\Nominatim\Requests\ReverseRequest;
use RuntimeException;

final readonly class NominatimPlaceNamer implements PlaceNamer
{
    /**
     * The parts of an address that name a place, the smallest first.
     */
    private const array PARTS = ['village', 'hamlet', 'town', 'suburb', 'city'];

    public function __construct(private NominatimConnector $nominatimConnector) {}

    public function nameAt(float $latitude, float $longitude): ?string
    {
        $response = $this->nominatimConnector->send(new ReverseRequest($latitude, $longitude));

        throw_if($response->failed(), RuntimeException::class, 'The place search did not answer.');

        $address = $response->json('address');

        foreach (self::PARTS as $part) {
            $name = is_array($address) ? ($address[$part] ?? null) : null;

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        // Out at sea the search answers with an error and no address.
        $name = $response->json('name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
