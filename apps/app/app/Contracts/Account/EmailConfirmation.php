<?php

declare(strict_types=1);

namespace App\Contracts\Account;

use App\Account\Email;
use App\Account\RiderId;

interface EmailConfirmation
{
    /**
     * Sends a confirmation link to the rider's current email.
     */
    public function request(RiderId $riderId): void;

    /**
     * Sends a link to a new email the rider asked for. Opening it, signed in, makes the change.
     */
    public function requestChange(RiderId $riderId, Email $email): void;
}
