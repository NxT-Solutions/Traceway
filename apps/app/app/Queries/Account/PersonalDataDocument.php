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
    required: ['exported_at', 'account', 'saved_routes', 'release_notes_read', 'connections', 'withheld'],
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
                new Property(property: 'last_seen_at', description: 'The last time the rider was signed in and using Traceway, to the quarter hour.', type: 'string', nullable: true),
                new Property(property: 'pending_email', description: 'A new email the rider asked for, waiting for them to open the link sent to it.', type: 'string', format: 'email', nullable: true),
                new Property(property: 'role', type: 'string', enum: ['super-admin', 'admin', 'user']),
            ],
        ),
        new Property(
            property: 'admin_actions',
            description: 'What the people who run Traceway did to this account, and when. Kept for a year.',
            type: 'array',
            items: new Items(
                required: ['action', 'at'],
                properties: [
                    new Property(property: 'action', type: 'string'),
                    new Property(property: 'at', type: 'string', nullable: true),
                ],
                type: 'object',
            ),
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
            property: 'connections',
            description: 'The accounts at other services the rider connected, to log in with or send routes to. The keys any of them gave are withheld.',
            type: 'array',
            items: new Items(
                required: ['service', 'account_number', 'account', 'allowed', 'connected_at', 'last_signed_in_at', 'last_sent_at', 'sent'],
                properties: [
                    new Property(property: 'service', type: 'string', enum: ['google', 'apple', 'facebook', 'instagram', 'x', 'github', 'discord', 'strava', 'garmin_connect', 'wahoo', 'ride_with_gps', 'runkeeper', 'runsignup', 'openstreetmap']),
                    new Property(property: 'account_number', description: "The service's own number for the rider's account there.", type: 'string', nullable: true),
                    new Property(property: 'account', description: 'The email or username the service shared, where Traceway may keep it.', type: 'string', nullable: true),
                    new Property(property: 'allowed', description: 'What the rider allowed Traceway to do, as the service names it.', type: 'array', items: new Items(type: 'string')),
                    new Property(property: 'connected_at', type: 'string', nullable: true),
                    new Property(property: 'last_signed_in_at', type: 'string', nullable: true),
                    new Property(property: 'last_sent_at', type: 'string', nullable: true),
                    new Property(
                        property: 'sent',
                        description: 'Each saved route, or leg, sent to the service. Leg 0 is the whole route.',
                        type: 'array',
                        items: new Items(
                            required: ['saved_route_id', 'leg', 'url', 'sent_at'],
                            properties: [
                                new Property(property: 'saved_route_id', type: 'string', format: 'uuid'),
                                new Property(property: 'leg', type: 'integer'),
                                new Property(property: 'url', type: 'string', nullable: true),
                                new Property(property: 'sent_at', type: 'string', nullable: true),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
                type: 'object',
            ),
        ),
        new Property(
            property: 'withheld',
            type: 'array',
            items: new Items(type: 'string', enum: ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'connection_keys']),
        ),
    ],
)]
final readonly class PersonalDataDocument
{
    /**
     * @param  list<ExportedRoute>  $routes
     * @param  list<array{tag: string, read_at: string|null}>  $releaseNotesRead
     * @param  list<array{service: string, account_number: string|null, account: string|null, allowed: list<string>, connected_at: string|null, last_signed_in_at: string|null, last_sent_at: string|null, sent: list<array{saved_route_id: string, leg: int, url: string|null, sent_at: string|null}>}>  $connections
     * @param  list<array{action: string, at: string|null}>  $adminActions
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
        public array $connections = [],
        public ?string $lastSeenAt = null,
        public string $role = 'user',
        public array $adminActions = [],
        public ?string $pendingEmail = null,
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
                'last_seen_at' => $this->lastSeenAt,
                'pending_email' => $this->pendingEmail,
                'role' => $this->role,
            ],
            'saved_routes' => array_map(
                fn (ExportedRoute $exportedRoute): array => $exportedRoute->toArray(),
                $this->routes,
            ),
            'release_notes_read' => $this->releaseNotesRead,
            'connections' => $this->connections,
            'admin_actions' => $this->adminActions,
            'withheld' => [
                'password',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'remember_token',
                'connection_keys',
            ],
        ];
    }
}
