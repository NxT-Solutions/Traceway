<?php

declare(strict_types=1);

namespace App\Queries\Routes;

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
