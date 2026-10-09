<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Email;
use App\Account\RiderName;

final readonly class PersonalDataDocument
{
    /**
     * @param  list<ExportedRoute>  $routes
     */
    public function __construct(
        public string $exportedAt,
        public RiderName $name,
        public Email $email,
        public ?string $emailVerifiedAt,
        public bool $twoFactorEnabled,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $routes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exported_at' => $this->exportedAt,
            'account' => [
                'name' => $this->name->value,
                'email' => $this->email->value,
                'email_verified_at' => $this->emailVerifiedAt,
                'two_factor_enabled' => $this->twoFactorEnabled,
                'created_at' => $this->createdAt,
                'updated_at' => $this->updatedAt,
            ],
            'saved_routes' => array_map(
                fn (ExportedRoute $exportedRoute): array => $exportedRoute->toArray(),
                $this->routes,
            ),
            'withheld' => [
                'password',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'remember_token',
            ],
        ];
    }
}
