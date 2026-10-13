<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\Details\RouteDetails;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'RouteStop',
    required: ['distance_metres', 'longitude', 'latitude', 'kind', 'name'],
    properties: [
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'longitude', type: 'number'),
        new Property(property: 'latitude', type: 'number'),
        new Property(property: 'kind', type: 'string', enum: ['water', 'toilet', 'cafe']),
        new Property(property: 'name', type: 'string', nullable: true),
    ],
)]
#[Schema(
    schema: 'RouteDetails',
    description: 'How a route climbs, what it runs on, where it turns, and where to stop.',
    required: ['profile', 'ascent_metres', 'descent_metres', 'surfaces', 'cues', 'stops'],
    properties: [
        new Property(
            property: 'profile',
            description: 'Pairs of metres from the start and the height of the ground in metres.',
            type: 'array',
            items: new Items(type: 'array', items: new Items(type: 'number'), maxItems: 2, minItems: 2),
        ),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(property: 'descent_metres', type: 'integer'),
        new Property(
            property: 'surfaces',
            type: 'array',
            items: new Items(
                required: ['surface', 'metres'],
                properties: [
                    new Property(property: 'surface', type: 'string', enum: ['paved', 'gravel', 'dirt', 'unknown']),
                    new Property(property: 'metres', type: 'integer'),
                ],
            ),
        ),
        new Property(
            property: 'cues',
            type: 'array',
            items: new Items(
                required: ['distance_metres', 'longitude', 'latitude', 'turn', 'street'],
                properties: [
                    new Property(property: 'distance_metres', type: 'integer'),
                    new Property(property: 'longitude', type: 'number'),
                    new Property(property: 'latitude', type: 'number'),
                    new Property(property: 'turn', type: 'string', enum: ['left', 'right', 'slight_left', 'slight_right', 'sharp_left', 'sharp_right', 'u_turn', 'straight']),
                    new Property(property: 'street', type: 'string', nullable: true),
                ],
            ),
        ),
        new Property(property: 'stops', type: 'array', items: new Items(ref: '#/components/schemas/RouteStop')),
    ],
)]
final readonly class RouteDetailsResponse
{
    public function __construct(public RouteDetails $routeDetails) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->routeDetails->toArray();
    }
}
