<?php

declare(strict_types=1);

namespace App\Domain\Usage;

/**
 * One daily total. A count goes up by one each time a rider, a watch, or an AI assistant uses that part of
 * Traceway. A gauge is written once a day. None of them says who.
 */
enum UsageMetric: string
{
    case RoutePreviews = 'route_previews';
    case RouteBuilds = 'route_builds';
    case MultisportPreviews = 'multisport_previews';
    case PointSnaps = 'point_snaps';
    case PlaceNames = 'place_names';
    case PlaceSearches = 'place_searches';
    case RouteImports = 'route_imports';
    case RoutesSaved = 'routes_saved';
    case FitExports = 'fit_exports';
    case RouteSends = 'route_sends';
    case SharedDownloads = 'shared_downloads';
    case DeviceSyncs = 'device_syncs';
    case DeviceDownloads = 'device_downloads';
    case AssistantRequests = 'assistant_requests';
    case SignIns = 'sign_ins';
    case ActiveRidersDay = 'active_riders_day';
    case ActiveRidersWeek = 'active_riders_week';
    case ActiveRidersMonth = 'active_riders_month';

    public function label(): string
    {
        return match ($this) {
            self::RoutePreviews => 'Routes drawn',
            self::RouteBuilds => 'Loops built',
            self::MultisportPreviews => 'Multisport routes drawn',
            self::PointSnaps => 'Points placed',
            self::PlaceNames => 'Places named',
            self::PlaceSearches => 'Place searches',
            self::RouteImports => 'Files imported',
            self::RoutesSaved => 'Routes saved',
            self::FitExports => 'FIT files exported',
            self::RouteSends => 'Routes sent to apps',
            self::SharedDownloads => 'Shared link downloads',
            self::DeviceSyncs => 'Watch syncs',
            self::DeviceDownloads => 'Courses sent to watches',
            self::AssistantRequests => 'AI assistant requests',
            self::SignIns => 'Sign-ins',
            self::ActiveRidersDay => 'Active riders, last day',
            self::ActiveRidersWeek => 'Active riders, last 7 days',
            self::ActiveRidersMonth => 'Active riders, last 30 days',
        };
    }

    /**
     * Written once a day as it stands, not counted up.
     */
    public function isGauge(): bool
    {
        return in_array($this, [self::ActiveRidersDay, self::ActiveRidersWeek, self::ActiveRidersMonth], strict: true);
    }

    /**
     * How many days back a rider must have been seen to count in this gauge.
     */
    public function activeDays(): ?int
    {
        return match ($this) {
            self::ActiveRidersDay => 1,
            self::ActiveRidersWeek => 7,
            self::ActiveRidersMonth => 30,
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function counts(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $metric): bool => ! $metric->isGauge()));
    }
}
