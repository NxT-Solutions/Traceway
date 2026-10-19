<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Account\Role;

/**
 * Gives a rider one role in place of any other.
 */
final readonly class AdminAssignRoleCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
        public Role $role,
    ) {}
}
