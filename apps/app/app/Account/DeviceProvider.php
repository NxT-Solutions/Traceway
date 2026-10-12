<?php

declare(strict_types=1);

namespace App\Account;

/**
 * The device app that pairs with Traceway. Each provider gets its own case and its own app or integration.
 */
enum DeviceProvider: string
{
    case Garmin = 'garmin';

    public function label(): string
    {
        return match ($this) {
            self::Garmin => 'Garmin',
        };
    }
}
