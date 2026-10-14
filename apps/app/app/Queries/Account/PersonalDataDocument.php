<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Account\Email;
use App\Account\RiderName;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'PersonalDataDocument',
    required: ['exported_at', 'account', 'saved_routes', 'release_notes_read', 'withheld'],
    properties: [
        new Property(property: 'exported_at', type: 'string', format: 'date-time'),
        new Property(
            property: 'account',
            required: ['name', 'email', 'two_factor_enabled'],
            properties: [
                new Property(property: 'name', type: 'string'),
                new Property(property: 'email', type: 'string', format: 'email'),
                new Property(property: 'email_verified_at', type: 'string', nullable: true),
                new Property(property: 'two_factor_enabled', type: 'boolean'),
                new Property(property: 'created_at', type: 'string', nullable: true),
                new Property(property: 'updated_at', type: 'string', nullable: true),
            ],
        ),
        new Property(property: 'saved_routes', type: 'array', items: new Items(type: 'object')),
        new Property(
            property: 'release_notes_read',
            type: 'array',
            items: new Items(
                required: ['tag', 'read_at'],
                properties: [
                    new Property(property: 'tag', type: 'string'),
                    new Property(property: 'read_at', type: 'string', nullable: true),
                ],
                type: 'object',
            ),
        ),
        new Property(
            property: 'withheld',
            type: 'array',
            items: new Items(type: 'string', enum: ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']),
        ),
    ],
)]
final readonly class PersonalDataDocument
{
    /**
     * @param  list<ExportedRoute>  $routes
     * @param  list<array{tag: string, read_at: string|null}>  $releaseNotesRead
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
        public array $releaseNotesRead,
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
            'release_notes_read' => $this->releaseNotesRead,
            'withheld' => [
                'password',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'remember_token',
            ],
        ];
    }
}
