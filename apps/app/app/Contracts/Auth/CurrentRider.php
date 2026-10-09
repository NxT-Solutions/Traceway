<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\Account\RiderId;

interface CurrentRider
{
    public function assertSame(RiderId $riderId): void;

    /**
     * A guest is allowed after Fortify has already checked a reset token.
     */
    public function assertSameOrGuest(RiderId $riderId): void;
}
