<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Account\RouteService;

final readonly class DisconnectRouteServiceCommand
{
    public function __construct(
        public RiderId $riderId,
        public RouteService $routeService,
    ) {}
}
