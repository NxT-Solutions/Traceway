<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\Distance;
use App\Domain\Routing\LineSimplifier;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'ImportedRoute',
    required: ['name', 'distance_metres', 'ascent_metres', 'coordinates'],
    properties: [
        new Property(property: 'name', type: 'string'),
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
    ],
)]
final readonly class ImportedRoute
{
    /** The planner sends a line back with at most this many points. */
    public const int MAX_POINTS = 8000;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public string $name,
        public array $coordinates,
        public ?int $distanceMetres = null,
    ) {}

    /**
     * @return array{name: string, distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>}
     */
    public function toArray(): array
    {
        $tolerance = LineSimplifier::ROUTE_METRES;
        $line = LineSimplifier::simplify($this->coordinates, $tolerance);

        while (count($line) > self::MAX_POINTS) {
            $tolerance *= 2;
            $line = LineSimplifier::simplify($this->coordinates, $tolerance);
        }

        return [
            'name' => $this->name,
            'distance_metres' => $this->distanceMetres ?? (int) round(Distance::along($this->coordinates)),
            'ascent_metres' => 0,
            'coordinates' => $line,
        ];
    }
}
