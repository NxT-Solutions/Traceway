<?php

declare(strict_types=1);

namespace App\Queries\Account;

final readonly class ConnectedApp
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $connectedAt,
    ) {}

    /**
     * @return array{id: string, name: string, connected_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'connected_at' => $this->connectedAt,
        ];
    }
}
