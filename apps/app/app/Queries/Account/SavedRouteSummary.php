<?php

declare(strict_types=1);

namespace App\Queries\Account;

final readonly class SavedRouteSummary
{
    public function __construct(
        public string $name,
        public string $sport,
        public int $distanceMetres,
        public ?string $createdAt,
    ) {}

    /**
     * @return array{name: string, sport: string, distance_metres: int, created_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'sport' => $this->sport,
            'distance_metres' => $this->distanceMetres,
            'created_at' => $this->createdAt,
        ];
    }
}
