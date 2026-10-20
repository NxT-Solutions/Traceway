<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * The owner of an email takes back an account someone opened with it before anyone confirmed it. Every way in
 * that the other person could have added goes. With a reset link the owner sets a new password; when a service
 * proved the address instead, the old password goes too, since whoever opened the account may have chosen it.
 */
final readonly class ClaimUnconfirmedAccountCommand
{
    public function __construct(
        public RiderId $riderId,
        public bool $passwordToo = false,
    ) {}
}
