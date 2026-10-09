<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;
use App\Routes\CoordinateLine;
use App\Routes\RouteName;

final readonly class SaveRouteCommand
{
    public function __construct(
        public RiderId $riderId,
        public RouteName $name,
        public Sport $sport,
        public Surface $surface,
        public ElevationBias $elevation,
        public string $roads,
        public int $distanceMetres,
        public int $ascentMetres,
        public CoordinateLine $coordinates,
        public ?CoordinateLine $userCoordinates,
        public ?CoordinateLine $finishCoordinates,
    ) {}
}
