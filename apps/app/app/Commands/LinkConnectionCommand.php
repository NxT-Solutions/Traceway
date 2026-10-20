<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectedIdentity;
use App\Account\RiderId;

/**
 * Connects an account at another service to the signed-in rider's Traceway account.
 */
final readonly class LinkConnectionCommand
{
    public function __construct(
        public RiderId $riderId,
        public ConnectedIdentity $connectedIdentity,
    ) {}
}
