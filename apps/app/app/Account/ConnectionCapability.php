<?php

declare(strict_types=1);

namespace App\Account;

/**
 * Something a connection lets Traceway do for the rider.
 */
enum ConnectionCapability: string
{
    case SignIn = 'sign_in';
    case SendRoutes = 'send_routes';
    case ShareRoutes = 'share_routes';
    case Notifications = 'notifications';
    case ImportRoutes = 'import_routes';
    case SyncRoutes = 'sync_routes';
    case MapNotes = 'map_notes';

    public function label(): string
    {
        return match ($this) {
            self::SignIn => 'Log in',
            self::SendRoutes => 'Send routes',
            self::ShareRoutes => 'Share routes',
            self::Notifications => 'Notifications',
            self::ImportRoutes => 'Bring in routes',
            self::SyncRoutes => 'Keep routes in sync',
            self::MapNotes => 'Report map problems',
        };
    }
}
