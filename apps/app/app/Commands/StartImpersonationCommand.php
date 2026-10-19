<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * A Super Admin starts seeing Traceway as a rider does, to help them.
 */
final readonly class StartImpersonationCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
    ) {}
}
