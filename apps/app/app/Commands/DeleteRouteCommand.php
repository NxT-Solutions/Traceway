<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Routes\RouteId;

final readonly class DeleteRouteCommand
{
    public function __construct(
        public RouteId $routeId,
        public RiderId $riderId,
    ) {}
}
