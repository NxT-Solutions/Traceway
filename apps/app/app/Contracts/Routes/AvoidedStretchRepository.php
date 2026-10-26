<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Routes\AvoidedStretchId;
use App\Routes\StretchToAvoid;

interface AvoidedStretchRepository
{
    /**
     * Keeps the stretch for the rider. The same way twice keeps the first,
     * and its id comes back.
     */
    public function add(RiderId $riderId, StretchToAvoid $stretchToAvoid): AvoidedStretchId;

    public function countFor(RiderId $riderId): int;

    public function deleteOwned(AvoidedStretchId $avoidedStretchId, RiderId $riderId): void;
}
