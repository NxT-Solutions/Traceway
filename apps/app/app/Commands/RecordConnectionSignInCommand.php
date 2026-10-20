<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectedIdentity;
use App\Account\RiderId;

/**
 * Notes that the rider logged in with a connected account, and keeps what the service said about them current.
 */
final readonly class RecordConnectionSignInCommand
{
    public function __construct(
        public RiderId $riderId,
        public ConnectedIdentity $connectedIdentity,
    ) {}
}
