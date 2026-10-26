<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Domain\Routing\AvoidedStretch;
use App\Domain\Routing\RoadEdge;
use App\Routes\AvoidedStretchId;

interface AvoidedStretchRepository
{
    /**
     * Keeps the stretch for the rider. The same way twice keeps the first.
     */
    public function add(RiderId $riderId, RoadEdge $roadEdge): AvoidedStretch;

    public function countFor(RiderId $riderId): int;

    public function deleteOwned(AvoidedStretchId $avoidedStretchId, RiderId $riderId): void;
}
