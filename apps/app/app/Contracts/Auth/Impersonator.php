<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\Account\RiderId;

/**
 * Lets a Super Admin see Traceway as a rider does, in their own browser, and come back.
 */
interface Impersonator
{
    public function start(RiderId $actorId, RiderId $riderId): void;

    /**
     * Signs the Super Admin back in as themselves.
     *
     * @return array{actor: RiderId, rider: RiderId}|null who acted as whom, or null when nobody was acting as anyone
     */
    public function stop(): ?array;
}
