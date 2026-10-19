<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * Turns two-factor sign-in off for a rider who lost their phone and recovery codes.
 */
final readonly class AdminResetTwoFactorCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
    ) {}
}
