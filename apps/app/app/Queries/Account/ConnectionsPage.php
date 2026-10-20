<?php

declare(strict_types=1);

namespace App\Queries\Account;

/**
 * The connections page: what the rider connected, and what they could add.
 */
final readonly class ConnectionsPage
{
    /**
     * @param  list<array<string, mixed>>  $connected
     * @param  list<array<string, mixed>>  $available
     */
    public function __construct(
        public array $connected,
        public array $available,
        public bool $hasPassword,
        public bool $hasPasskey,
    ) {}

    /**
     * @return array{connected: list<array<string, mixed>>, available: list<array<string, mixed>>, hasPassword: bool, hasPasskey: bool}
     */
    public function toArray(): array
    {
        return [
            'connected' => $this->connected,
            'available' => $this->available,
            'hasPassword' => $this->hasPassword,
            'hasPasskey' => $this->hasPasskey,
        ];
    }
}
