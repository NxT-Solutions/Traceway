<?php

declare(strict_types=1);

namespace App\Contracts\Routes;

use App\Account\RiderId;
use App\Routes\AvoidedStretchId;
use App\Routes\StretchToAvoid;
use App\Routes\TooManyAvoidedStretches;

interface AvoidedStretchRepository
{
    /**
     * Keeps the stretch for the rider. The same way twice keeps the first,
     * and its id comes back, even for a rider who keeps the most.
     *
     * @throws TooManyAvoidedStretches when the rider already keeps the most.
     */
    public function add(RiderId $riderId, StretchToAvoid $stretchToAvoid, int $most): AvoidedStretchId;

    public function deleteOwned(AvoidedStretchId $avoidedStretchId, RiderId $riderId): void;
}
