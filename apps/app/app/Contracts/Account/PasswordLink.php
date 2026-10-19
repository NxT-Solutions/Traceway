<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\RiderId;

interface PasswordLink
{
    /**
     * Sends the rider a link to choose a new password.
     */
    public function send(RiderId $riderId): void;
}
