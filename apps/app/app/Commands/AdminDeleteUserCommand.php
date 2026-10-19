<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * Deletes a rider's account from the admin area, as if they had deleted it themselves.
 */
final readonly class AdminDeleteUserCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
    ) {}
}
