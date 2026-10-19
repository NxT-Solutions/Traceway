<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * Signs a rider out on every device.
 */
final readonly class AdminEndSessionsCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
    ) {}
}
