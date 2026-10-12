<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\LineSimplifier;
use LogicException;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'RoutePreview',
    required: ['distance_metres', 'ascent_metres', 'coordinates', 'routes'],
    properties: [
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(
            property: 'routes',
            type: 'array',
            items: new Items(
                required: ['distance_metres', 'ascent_metres', 'coordinates'],
                properties: [
                    new Property(property: 'distance_metres', type: 'integer'),
                    new Property(property: 'ascent_metres', type: 'integer'),
                    new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
                ],
            ),
        ),
    ],
)]
final readonly class RoutePreview
{
    /**
     * @param  list<GeneratedRoute>  $routes
     */
    public function __construct(public array $routes) {}

    /**
     * @param  list<GeneratedRoute>  $routes
     */
    public static function from(array $routes): self
    {
        return new self($routes);
    }

    /**
     * @return array{
     *     distance_metres: int,
     *     ascent_metres: int,
     *     coordinates: list<array{0: float, 1: float}>,
     *     routes: list<array{distance_metres: int, ascent_metres: int, coordinates: list<array{0: float, 1: float}>}>
     * }
     */
    public function toArray(): array
    {
        $first = $this->routes[0] ?? throw new LogicException('A preview needs a route.');
        // Distances come from the full line. Only the points sent are thinned.
        $lines = array_map(
            fn (GeneratedRoute $generatedRoute): array => LineSimplifier::simplify($generatedRoute->coordinates, LineSimplifier::ROUTE_METRES),
            $this->routes,
        );

        return [
            'distance_metres' => $first->distanceMetres,
            'ascent_metres' => $first->ascentMetres,
            'coordinates' => $lines[0],
            'routes' => array_map(
                fn (GeneratedRoute $generatedRoute, array $line): array => [
                    'distance_metres' => $generatedRoute->distanceMetres,
                    'ascent_metres' => $generatedRoute->ascentMetres,
                    'coordinates' => $line,
                ],
                $this->routes,
                $lines,
            ),
        ];
    }
}
