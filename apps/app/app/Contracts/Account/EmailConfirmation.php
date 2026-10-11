<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\RiderId;

interface EmailConfirmation
{
    /**
     * Sends a confirmation link to the rider's current email.
     */
    public function request(RiderId $riderId): void;
}
