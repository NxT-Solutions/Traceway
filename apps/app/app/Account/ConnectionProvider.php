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
    case Apple = 'apple';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case X = 'x';
    case GitHub = 'github';
    case Discord = 'discord';
    case Strava = 'strava';
    case GarminConnect = 'garmin_connect';
    case Wahoo = 'wahoo';
    case RideWithGps = 'ride_with_gps';
    case Runkeeper = 'runkeeper';
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
            self::Apple => 'Apple',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::X => 'X',
            self::GitHub => 'GitHub',
            self::Discord => 'Discord',
            self::Strava => 'Strava',
            self::GarminConnect => 'Garmin Connect',
            self::Wahoo => 'Wahoo',
            self::RideWithGps => 'Ride with GPS',
            self::Runkeeper => 'Runkeeper',
            self::RunSignup => 'RunSignup',
            self::OpenStreetMap => 'OpenStreetMap',
        };
    }

    public function group(): ConnectionGroup
    {
        return match ($this) {
            self::Strava, self::GarminConnect, self::Wahoo, self::RideWithGps, self::Runkeeper, self::RunSignup => ConnectionGroup::Sport,
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
            self::Apple => 'Log in with your Apple Account. Apple can keep your email private.',
            self::Instagram => 'Log in with an Instagram business or creator account.',
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
            self::Facebook, self::Instagram, self::X => [ConnectionCapability::ShareRoutes],
            self::Discord, self::RunSignup => [ConnectionCapability::Notifications],
            self::Strava, self::RideWithGps, self::Runkeeper => [ConnectionCapability::ImportRoutes],
            self::GarminConnect => [ConnectionCapability::SendRoutes],
            self::Wahoo => [ConnectionCapability::SyncRoutes],
            self::OpenStreetMap => [ConnectionCapability::MapNotes],
            self::Google, self::Apple, self::GitHub => [],
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
            self::Apple => ['Your Apple account number and the email Apple shares, which can be a private relay address.', 'No password, and no access to your Apple Account.'],
            self::Facebook => ['Your Facebook account number for Traceway, and your email address if Facebook shares it.', 'No password, and no access to your posts or friends.'],
            self::Instagram => ['Your Instagram account number for Traceway and your username.', 'No password, and no access to your posts.'],
            self::X => ['Your X account number and username.', 'No password, and no access to your posts.'],
            self::GitHub => ['Your GitHub account number and username.', 'No password, and no access to your code.'],
            self::Discord => ['Your Discord account number and username.', 'No password, and no access to your messages.'],
            self::Strava => ['Your Strava athlete number.', 'No activities, and no access to your Strava account.'],
            self::GarminConnect => ['Your Garmin Connect user number.', 'The keys Garmin gave Traceway, kept encrypted, so Traceway can remove its access when you disconnect.', 'No activities or health data.'],
            self::Wahoo => ['Your Wahoo account number and email address.', 'The keys Wahoo gave Traceway, kept encrypted, to send routes.', 'Which routes you sent there.'],
            self::RideWithGps => ['Your Ride with GPS account number and email address.', 'The key Ride with GPS gave Traceway, kept encrypted, to send routes.', 'Which routes you sent there.'],
            self::Runkeeper => ['Your Runkeeper account number.', 'No activities, and no access to your Runkeeper account.'],
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
            self::Instagram => 'Business and creator accounts only.',
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
            self::Apple => 'https://account.apple.com/account/manage',
            self::Facebook => 'https://www.facebook.com/settings/?tab=applications',
            self::Instagram => 'https://www.instagram.com/accounts/manage_access/',
            self::X => 'https://x.com/settings/connected_apps',
            self::GitHub => 'https://github.com/settings/applications',
            self::Strava => 'https://www.strava.com/settings/apps',
            self::Runkeeper => 'https://runkeeper.com/settings/apps',
            self::RunSignup => 'https://runsignup.com/Profile/OAuth2',
            self::OpenStreetMap => 'https://www.openstreetmap.org/oauth2/authorized_applications',
            self::Discord, self::GarminConnect, self::Wahoo, self::RideWithGps => null,
        };
    }

    /**
     * True when Traceway keeps the keys the service gave it: to send routes, or to remove its own access when the
     * rider disconnects. Every other connection keeps only who the rider is there.
     */
    public function keepsKeys(): bool
    {
        return match ($this) {
            self::Wahoo, self::RideWithGps, self::GarminConnect => true,
            default => false,
        };
    }

    /**
     * False for services whose account Traceway knows only by its number: Strava, whose terms limit how long its
     * data is kept, and Garmin Connect and Runkeeper, which share no name. Their name is not kept, nor offered as
     * the rider's account name, and the connection shows only when it was made.
     */
    public function keepsAccountLabel(): bool
    {
        return match ($this) {
            self::Strava, self::GarminConnect, self::Runkeeper => false,
            default => true,
        };
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
            self::Apple => 2,
            self::Strava => 3,
            self::GarminConnect => 4,
            self::Facebook => 5,
            self::Wahoo => 6,
            self::RideWithGps => 7,
            self::X => 8,
            self::GitHub => 9,
            self::Discord => 10,
            self::Instagram => 11,
            self::OpenStreetMap => 12,
            self::Runkeeper => 13,
            self::RunSignup => 14,
        };
    }
}
