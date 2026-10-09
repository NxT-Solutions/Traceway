<?php

declare(strict_types=1);

namespace App\Queries\Account;

final readonly class ExportedRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  list<array{0: float, 1: float}>|null  $userCoordinates
     * @param  list<array{0: float, 1: float}>|null  $finishCoordinates
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
        public ?array $userCoordinates,
        public ?array $finishCoordinates,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
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
            'user_coordinates' => $this->userCoordinates,
            'finish_coordinates' => $this->finishCoordinates,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
