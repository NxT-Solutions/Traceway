<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\AvoidCheck;
use App\Domain\Routing\Distance;
use App\Domain\Routing\LineSimplifier;
use App\Domain\Routing\Reshaped;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RouteQuery;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'ReshapedLoop',
    required: ['distance_metres', 'ascent_metres', 'coordinates', 'waypoints', 'changed_legs', 'reverse_share', 'crosses_avoided'],
    properties: [
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', description: 'Unknown until the details are read: 0.', type: 'integer'),
        new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'waypoints', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'changed_legs', description: 'The legs planned again, by number, from 0.', type: 'array', items: new Items(type: 'integer')),
        new Property(property: 'reverse_share', description: 'The share of the loop that rides back over a road it already used, from 0 to 1.', type: 'number'),
        new Property(property: 'crosses_avoided', type: 'array', items: new Items(type: 'string', format: 'uuid')),
    ],
)]
final readonly class ReshapedLoop
{
    public function __construct(
        public Reshaped $reshaped,
        public RouteQuery $routeQuery,
    ) {}

    public static function from(Reshaped $reshaped, RouteQuery $routeQuery): self
    {
        return new self($reshaped, $routeQuery);
    }

    /**
     * @return array{distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>, waypoints: list<array{0: float, 1: float}>, changed_legs: list<int>, reverse_share: float, crosses_avoided: list<string>}
     */
    public function toArray(): array
    {
        // A spur the rider rode to on purpose, to reach a point they placed, stays.
        $line = RouteCleanliness::keepingVisits(
            RouteCleanliness::withoutSpurs($this->reshaped->coordinates),
            $this->reshaped->coordinates,
            $this->reshaped->waypoints,
        );

        return [
            'distance_metres' => $line === $this->reshaped->coordinates ? $this->reshaped->distanceMetres : (int) round(Distance::along($line)),
            'ascent_metres' => 0,
            'coordinates' => LineSimplifier::simplify($line, LineSimplifier::ROUTE_METRES),
            'waypoints' => $this->reshaped->waypoints,
            'changed_legs' => $this->reshaped->changed,
            'reverse_share' => round(RouteCleanliness::reverseShare($line), 3),
            'crosses_avoided' => AvoidCheck::crossing($line, $this->routeQuery->avoid),
        ];
    }
}
