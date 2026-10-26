<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Email;
use App\Account\RiderName;

final readonly class PrivacyPage
{
    /**
     * @param  list<SavedRouteSummary>  $routes
     * @param  list<array{name: string|null, created_at: string|null}>  $avoided
     */
    public function __construct(
        public RiderName $name,
        public Email $email,
        public ?string $emailVerifiedAt,
        public bool $twoFactorEnabled,
        public ?string $createdAt,
        public array $routes,
        public array $avoided = [],
    ) {}

    /**
     * @return array{
     *     account: array{name: string, email: string, email_verified_at: string|null, two_factor_enabled: bool, created_at: string|null},
     *     routes: list<array{name: string, sport: string, distance_metres: int, created_at: string|null}>,
     *     avoided: list<array{name: string|null, created_at: string|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'account' => [
                'name' => $this->name->value,
                'email' => $this->email->value,
                'email_verified_at' => $this->emailVerifiedAt,
                'two_factor_enabled' => $this->twoFactorEnabled,
                'created_at' => $this->createdAt,
            ],
            'routes' => array_map(
                fn (SavedRouteSummary $savedRouteSummary): array => $savedRouteSummary->toArray(),
                $this->routes,
            ),
            'avoided' => $this->avoided,
        ];
    }
}
