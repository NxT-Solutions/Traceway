<?php

declare(strict_types=1);

namespace App\Domain\Status;

/**
 * One part of Traceway a rider depends on, as the status page names it.
 */
enum StatusComponent: string
{
    case Planner = 'planner';
    case Routing = 'routing';
    /** Draws routes, without the climb, while route drawing is down. */
    case RoutingBackup = 'routing_backup';
    case Places = 'places';
    case Tiles = 'tiles';
    case RideWithGps = 'ride_with_gps';
    case Wahoo = 'wahoo';
    case GarminStore = 'garmin_store';

    public function label(): string
    {
        return match ($this) {
            self::Planner => 'Planner',
            self::Routing => 'Route drawing',
            self::RoutingBackup => 'Backup route drawing',
            self::Places => 'Place search',
            self::Tiles => 'Maps',
            self::RideWithGps => 'Ride with GPS',
            self::Wahoo => 'Wahoo',
            self::GarminStore => 'Garmin app in the Connect IQ store',
        };
    }

    public function group(): StatusGroup
    {
        return match ($this) {
            self::Planner => StatusGroup::Traceway,
            self::Routing, self::RoutingBackup, self::Places, self::Tiles => StatusGroup::Maps,
            self::RideWithGps, self::Wahoo => StatusGroup::Apps,
            self::GarminStore => StatusGroup::Devices,
        };
    }

    /**
     * True when the planner cannot draw or find a route without it, so the map tells the rider.
     */
    public function plannerNeeds(): bool
    {
        return in_array($this, [self::Routing, self::Places, self::Tiles], strict: true);
    }

    /**
     * What a rider reads about this part in one state, such as "Route drawing is slow right now".
     */
    public function describe(ServiceState $serviceState): string
    {
        if ($this === self::GarminStore) {
            return match ($serviceState) {
                ServiceState::Up => 'The Garmin app is in the Connect IQ store',
                ServiceState::Waiting => 'The Garmin app is waiting for Garmin to approve it',
                ServiceState::Slow => 'The Connect IQ store is slow right now',
                ServiceState::Down => 'The Connect IQ store cannot be reached right now',
            };
        }

        return match ($serviceState) {
            ServiceState::Up, ServiceState::Waiting => $this->label().' works',
            ServiceState::Slow => $this->label().' is slow right now',
            ServiceState::Down => $this->label().' is down right now',
        };
    }
}
