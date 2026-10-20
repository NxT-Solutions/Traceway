<?php

declare(strict_types=1);

namespace App\Account;

/**
 * How the sign-in options and the connections page group the services, so a rider finds theirs at a glance.
 */
enum ConnectionGroup: string
{
    case Sport = 'sport';
    case Accounts = 'accounts';
    case Maps = 'maps';

    public function label(): string
    {
        return match ($this) {
            self::Sport => 'Sport apps',
            self::Accounts => 'Everyday accounts',
            self::Maps => 'Maps',
        };
    }
}
