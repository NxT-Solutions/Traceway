<?php

declare(strict_types=1);

namespace App\Account;

/**
 * A service a rider connects so Traceway can send routes to it. A device app pairs instead; see DeviceProvider.
 */
enum RouteService: string
{
    case Wahoo = 'wahoo';
    case RideWithGps = 'ride_with_gps';

    public function label(): string
    {
        return match ($this) {
            self::Wahoo => 'Wahoo',
            self::RideWithGps => 'Ride with GPS',
        };
    }
}
