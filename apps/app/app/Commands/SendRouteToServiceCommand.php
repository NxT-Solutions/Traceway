<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Account\RouteService;
use App\Routes\RouteId;

/**
 * Sends a saved route, or one land leg of a multisport plan, to a connected service. Leg 0 is the whole route.
 */
final readonly class SendRouteToServiceCommand
{
    public function __construct(
        public RiderId $riderId,
        public RouteId $routeId,
        public int $leg,
        public RouteService $routeService,
    ) {}
}
