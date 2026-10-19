<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\Account\Permission;
use App\Account\RiderId;

interface AdminAccess
{
    /**
     * Stops the write unless the person asking holds the permission. A Super Admin holds every one.
     */
    public function assertAllowed(RiderId $riderId, Permission $permission): void;

    /**
     * Stops the write unless the person asking holds the permission and may act on this rider: never themselves,
     * and a Super Admin only by another Super Admin.
     */
    public function assertMayManage(RiderId $actorId, RiderId $riderId, Permission $permission): void;
}
