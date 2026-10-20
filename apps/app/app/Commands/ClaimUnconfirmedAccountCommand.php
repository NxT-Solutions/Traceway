<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * The owner of an email takes back an account someone opened with it before anyone confirmed it. Every way in
 * that the other person could have added goes.
 */
final readonly class ClaimUnconfirmedAccountCommand
{
    public function __construct(
        public RiderId $riderId,
    ) {}
}
