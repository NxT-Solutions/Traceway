<?php

declare(strict_types=1);

namespace App\Account;

/**
 * An account at another service that a rider connects to Traceway. Every connection logs the rider in. Some do
 * more, such as sending routes, and some will do more later. The slug is also the Socialite driver and the
 * config/services.php key.
 */
enum ConnectionProvider: string
{
    case Google = 'google';
    case GitHub = 'github';
    case Discord = 'discord';
    case Wahoo = 'wahoo';
    case RideWithGps = 'ride_with_gps';
    case RunSignup = 'runsignup';
    case OpenStreetMap = 'openstreetmap';

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(static fn (self $connectionProvider): string => $connectionProvider->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
            self::Discord => 'Discord',
            self::Wahoo => 'Wahoo',
            self::RideWithGps => 'Ride with GPS',
            self::RunSignup => 'RunSignup',
            self::OpenStreetMap => 'OpenStreetMap',
        };
    }

    public function group(): ConnectionGroup
    {
        return match ($this) {
            self::Wahoo, self::RideWithGps, self::RunSignup => ConnectionGroup::Sport,
            self::OpenStreetMap => ConnectionGroup::Maps,
            default => ConnectionGroup::Accounts,
        };
    }

    /**
     * One line on what connecting does today.
     */
    public function purpose(): string
    {
        return match ($this) {
            self::Wahoo => 'Log in with Wahoo, and send routes to your ELEMNT or ACE.',
            self::RideWithGps => 'Log in with Ride with GPS, and send routes to your library there.',
            self::OpenStreetMap => 'Log in with the account you map with.',
            default => 'Log in with your '.$this->label().' account.',
        };
    }

    /**
     * What the connection does today. Every connection logs the rider in.
     *
     * @return list<ConnectionCapability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Wahoo, self::RideWithGps => [ConnectionCapability::SignIn, ConnectionCapability::SendRoutes],
            default => [ConnectionCapability::SignIn],
        };
    }

    /**
     * What the connection may do later. Each needs the rider to allow it again when it arrives.
     *
     * @return list<ConnectionCapability>
     */
    public function later(): array
    {
        return match ($this) {
            self::Discord, self::RunSignup => [ConnectionCapability::Notifications],
            self::RideWithGps => [ConnectionCapability::ImportRoutes],
            self::Wahoo => [ConnectionCapability::SyncRoutes],
            self::OpenStreetMap => [ConnectionCapability::MapNotes],
            self::Google, self::GitHub => [],
        };
    }

    /**
     * What Traceway keeps about the connection, in plain words, for the rider deciding whether to connect.
     *
     * @return list<string>
     */
    public function keeps(): array
    {
        return match ($this) {
            self::Google => ['Your Google account number and email address.', 'No password, and no access to your Google account.'],
            self::GitHub => ['Your GitHub account number and username.', 'No password, and no access to your code.'],
            self::Discord => ['Your Discord account number and username.', 'No password, and no access to your messages.'],
            self::Wahoo => ['Your Wahoo account number and email address.', 'The keys Wahoo gave Traceway, kept encrypted, to send routes.', 'Which routes you sent there.'],
            self::RideWithGps => ['Your Ride with GPS account number and email address.', 'The key Ride with GPS gave Traceway, kept encrypted, to send routes.', 'Which routes you sent there.'],
            self::RunSignup => ['Your RunSignup account number and email address.', 'No races, and no access to your RunSignup account.'],
            self::OpenStreetMap => ['Your OpenStreetMap account number and display name.', 'No home location, and no access to your edits.'],
        };
    }

    /**
     * A limit the rider should know before connecting.
     */
    public function note(): ?string
    {
        return match ($this) {
            self::RunSignup => 'The account you sign up for races with.',
            default => null,
        };
    }

    /**
     * Where the rider removes Traceway at the service itself. Null when Traceway removes its own access on
     * disconnect, or the service has no such page on the web.
     */
    public function manageUrl(): ?string
    {
        return match ($this) {
            self::Google => 'https://myaccount.google.com/connections',
            self::GitHub => 'https://github.com/settings/applications',
            self::RunSignup => 'https://runsignup.com/Profile/OAuth2',
            self::OpenStreetMap => 'https://www.openstreetmap.org/oauth2/authorized_applications',
            self::Discord, self::Wahoo, self::RideWithGps => null,
        };
    }

    /**
     * True when Traceway keeps the keys the service gave it, to send routes. Every other connection keeps only who
     * the rider is there.
     */
    public function keepsKeys(): bool
    {
        return match ($this) {
            self::Wahoo, self::RideWithGps => true,
            default => false,
        };
    }

    /**
     * Whether Traceway keeps the name the service shows for the account. Every service here allows it. One that
     * shares only a number, or limits how long its data is kept, returns false: its name is not kept, nor offered
     * as the rider's account name, and the connection shows only when it was made.
     */
    public function keepsAccountLabel(): bool
    {
        return true;
    }

    /**
     * The service Send to device puts routes on, for a connection that sends routes.
     */
    public function routeService(): ?RouteService
    {
        return RouteService::tryFrom($this->value);
    }

    /**
     * The order the sign-in page offers connections in before riders have used any. Lower comes first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Google => 1,
            self::Wahoo => 2,
            self::RideWithGps => 3,
            self::GitHub => 4,
            self::Discord => 5,
            self::OpenStreetMap => 6,
            self::RunSignup => 7,
        };
    }
}
