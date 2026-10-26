<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\AvoidedStretch;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'AvoidedStretch',
    description: 'A stretch of road the router skips for this rider: one way between two junctions.',
    required: ['id', 'name', 'latitude', 'longitude', 'shape', 'sport', 'surface'],
    properties: [
        new Property(property: 'id', type: 'string', format: 'uuid'),
        new Property(property: 'name', description: 'The street or path name, when it has one.', type: 'string', nullable: true),
        new Property(property: 'latitude', description: 'A point on the stretch.', type: 'number'),
        new Property(property: 'longitude', description: 'A point on the stretch.', type: 'number'),
        new Property(property: 'shape', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'sport', description: 'The sport the stretch was found for. One found on foot is not sent with a bike plan.', type: 'string', enum: ['road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
        new Property(property: 'surface', type: 'string', enum: ['paved', 'mixed', 'dirt']),
    ],
)]
final readonly class AvoidedStretchResponse
{
    public function __construct(public AvoidedStretch $avoidedStretch) {}

    /**
     * @return array{id: string, name: string|null, latitude: float, longitude: float, shape: list<array{0: float, 1: float}>, sport: string, surface: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->avoidedStretch->id,
            'name' => $this->avoidedStretch->name,
            'latitude' => $this->avoidedStretch->latitude,
            'longitude' => $this->avoidedStretch->longitude,
            'shape' => $this->avoidedStretch->shape,
            'sport' => $this->avoidedStretch->sport->value,
            'surface' => $this->avoidedStretch->surface->value,
        ];
    }
}
