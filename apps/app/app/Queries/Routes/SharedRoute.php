<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'SharedRoute',
    required: ['name', 'sport', 'surface', 'elevation', 'roads', 'distance_metres', 'ascent_metres', 'coordinates', 'gpx_url', 'fit_url', 'image_url', 'layout', 'legs', 'details'],
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
        new Property(property: 'image_url', description: 'The picture chats show for the link: the route on the map, 1200 by 630.', type: 'string'),
        new Property(property: 'layout', type: 'string', nullable: true, enum: ['loops', 'chain']),
        new Property(property: 'details', ref: '#/components/schemas/RouteDetails', description: 'The climb, surfaces, turns, and stops. Null for a multisport plan or a route saved before they were read.', nullable: true),
        new Property(
            property: 'legs',
            description: 'The legs of a multisport plan, in order. Empty for a single sport.',
            type: 'array',
            items: new Items(
                required: ['discipline', 'distance_metres', 'ascent_metres', 'coordinates', 'fit_url'],
                properties: [
                    new Property(property: 'discipline', type: 'string', enum: ['swim', 'road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
                    new Property(property: 'distance_metres', type: 'integer'),
                    new Property(property: 'ascent_metres', type: 'integer'),
                    new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
                    new Property(property: 'fit_url', description: 'No course for the swim.', type: 'string', nullable: true),
                    new Property(property: 'color', description: 'The colour picked for the leg, as #rrggbb.', type: 'string', nullable: true),
                    new Property(property: 'details', ref: '#/components/schemas/RouteDetails', description: 'The climb, surfaces, turns, and stops of a land leg. Null for the swim or a plan saved before they were read.', nullable: true),
                ],
            ),
        ),
    ],
)]
final readonly class SharedRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, color: string|null, fit_url: string|null, details: array<string, mixed>|null}>  $legs
     * @param  array<string, mixed>|null  $details
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
        public ?string $layout = null,
        public array $legs = [],
        public ?array $details = null,
        public string $imageUrl = '',
        public bool $imageDrawn = false,
        public int $riderId = 0,
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
     *     fit_url: string,
     *     image_url: string,
     *     layout: string|null,
     *     legs: list<array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, color: string|null, fit_url: string|null, details: array<string, mixed>|null}>,
     *     details: array<string, mixed>|null
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
            'image_url' => $this->imageUrl,
            'layout' => $this->layout,
            'legs' => $this->legs,
            'details' => $this->details,
        ];
    }
}
