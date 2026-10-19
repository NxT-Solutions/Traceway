<?php

declare(strict_types=1);

namespace App\Account;

/**
 * One thing done to a rider's account from the admin area or the server, kept in the activity log.
 */
enum AdminAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case ConfirmedEmail = 'confirmed_email';
    case RoleChanged = 'role_changed';
    case TwoFactorReset = 'two_factor_reset';
    case SignedOut = 'signed_out';
    case Deleted = 'deleted';
    case ImpersonationStarted = 'impersonation_started';
    case ImpersonationStopped = 'impersonation_stopped';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Opened the account',
            self::Updated => 'Changed the name or email',
            self::ConfirmedEmail => 'Marked the email as confirmed',
            self::RoleChanged => 'Changed the role',
            self::TwoFactorReset => 'Turned two-factor sign-in off',
            self::SignedOut => 'Signed out everywhere',
            self::Deleted => 'Deleted the account',
            self::ImpersonationStarted => 'Started acting as the rider',
            self::ImpersonationStopped => 'Stopped acting as the rider',
        };
    }
}
