<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Account\RouteService;
use SensitiveParameter;

final readonly class ConnectRouteServiceCommand
{
    public function __construct(
        public RiderId $riderId,
        public RouteService $routeService,
        #[SensitiveParameter] public string $code,
    ) {}
}
