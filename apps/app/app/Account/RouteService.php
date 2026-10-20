<?php

declare(strict_types=1);

namespace App\Account;

/**
 * A service Traceway sends routes to, through the rider's connection there. A device app pairs instead; see
 * DeviceProvider.
 */
enum RouteService: string
{
    case Wahoo = 'wahoo';
    case RideWithGps = 'ride_with_gps';

    public function label(): string
    {
        return $this->connection()->label();
    }

    /**
     * The connection the rider makes to send here. It logs them in as well.
     */
    public function connection(): ConnectionProvider
    {
        return match ($this) {
            self::Wahoo => ConnectionProvider::Wahoo,
            self::RideWithGps => ConnectionProvider::RideWithGps,
        };
    }
}
