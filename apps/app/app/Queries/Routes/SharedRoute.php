<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'SharedRoute',
    required: ['name', 'sport', 'surface', 'elevation', 'roads', 'distance_metres', 'ascent_metres', 'coordinates', 'gpx_url', 'fit_url'],
    properties: [
        new Property(property: 'name', type: 'string'),
        new Property(property: 'sport', type: 'string'),
        new Property(property: 'surface', type: 'string'),
        new Property(property: 'elevation', type: 'string'),
        new Property(property: 'roads', type: 'string'),
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'gpx_url', type: 'string'),
        new Property(property: 'fit_url', type: 'string'),
    ],
)]
final readonly class SharedRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public string $name,
        public string $sport,
        public string $surface,
        public string $elevation,
        public string $roads,
        public int $distanceMetres,
        public int $ascentMetres,
        public array $coordinates,
        public string $gpxUrl,
        public string $fitUrl,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     sport: string,
     *     surface: string,
     *     elevation: string,
     *     roads: string,
     *     distance_metres: int,
     *     ascent_metres: int,
     *     coordinates: list<array{0: float, 1: float}>,
     *     gpx_url: string,
     *     fit_url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sport' => $this->sport,
            'surface' => $this->surface,
            'elevation' => $this->elevation,
            'roads' => $this->roads,
            'distance_metres' => $this->distanceMetres,
            'ascent_metres' => $this->ascentMetres,
            'coordinates' => $this->coordinates,
            'gpx_url' => $this->gpxUrl,
            'fit_url' => $this->fitUrl,
        ];
    }
}
