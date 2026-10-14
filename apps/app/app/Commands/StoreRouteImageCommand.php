<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Routes\RouteId;

final readonly class StoreRouteImageCommand
{
    public function __construct(
        public RiderId $riderId,
        public RouteId $routeId,
        public string $jpeg,
    ) {}
}
