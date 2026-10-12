<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Routes\RouteId;

final readonly class LibraryRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<array{discipline: string, distance_metres: int, fit_url: string|null}>  $legs
     */
    public function __construct(
        public RouteId $id,
        public string $name,
        public string $sport,
        public string $surface,
        public string $elevation,
        public string $roads,
        public int $distanceMetres,
        public int $ascentMetres,
        public string $url,
        public string $fitUrl,
        public ?string $createdAt,
        public array $coordinates,
        public ?string $layout = null,
        public array $legs = [],
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     sport: string,
     *     surface: string,
     *     elevation: string,
     *     roads: string,
     *     distance_metres: int,
     *     ascent_metres: int,
     *     url: string,
     *     fit_url: string,
     *     created_at: string|null,
     *     coordinates: list<array{0: float, 1: float}>,
     *     layout: string|null,
     *     legs: list<array{discipline: string, distance_metres: int, fit_url: string|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'name' => $this->name,
            'sport' => $this->sport,
            'surface' => $this->surface,
            'elevation' => $this->elevation,
            'roads' => $this->roads,
            'distance_metres' => $this->distanceMetres,
            'ascent_metres' => $this->ascentMetres,
            'url' => $this->url,
            'fit_url' => $this->fitUrl,
            'created_at' => $this->createdAt,
            'coordinates' => $this->coordinates,
            'layout' => $this->layout,
            'legs' => $this->legs,
        ];
    }
}
