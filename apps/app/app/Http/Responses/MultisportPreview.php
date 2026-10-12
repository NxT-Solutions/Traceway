<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\MultisportRoute;
use App\Domain\Routing\PlannedLeg;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'MultisportPreview',
    required: ['layout', 'distance_metres', 'ascent_metres', 'legs'],
    properties: [
        new Property(property: 'layout', type: 'string', enum: ['loops', 'chain']),
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(
            property: 'legs',
            type: 'array',
            items: new Items(
                required: ['discipline', 'distance_metres', 'ascent_metres', 'coordinates'],
                properties: [
                    new Property(property: 'discipline', type: 'string', enum: ['swim', 'road', 'gravel', 'mtb', 'run', 'trail_run', 'walk', 'hike']),
                    new Property(property: 'distance_metres', type: 'integer'),
                    new Property(property: 'ascent_metres', type: 'integer'),
                    new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
                ],
            ),
        ),
    ],
)]
final readonly class MultisportPreview
{
    public function __construct(public MultisportRoute $multisportRoute) {}

    public static function from(MultisportRoute $multisportRoute): self
    {
        return new self($multisportRoute);
    }

    /**
     * @return array{
     *     layout: string,
     *     distance_metres: int,
     *     ascent_metres: int,
     *     legs: list<array{discipline: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'layout' => $this->multisportRoute->layout->value,
            'distance_metres' => $this->multisportRoute->distanceMetres(),
            'ascent_metres' => $this->multisportRoute->ascentMetres(),
            'legs' => array_map(
                fn (PlannedLeg $plannedLeg): array => [
                    'discipline' => $plannedLeg->discipline(),
                    'distance_metres' => $plannedLeg->distanceMetres,
                    'ascent_metres' => $plannedLeg->ascentMetres,
                    'coordinates' => $plannedLeg->coordinates,
                ],
                $this->multisportRoute->legs,
            ),
        ];
    }
}
