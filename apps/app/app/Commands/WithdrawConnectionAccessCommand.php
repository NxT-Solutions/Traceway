<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectedIdentity;

/**
 * Hands back the keys a service gave for an identity Traceway did not keep, such as a sign-up the rider
 * cancelled, so the service does not go on trusting Traceway for someone without an account.
 */
final readonly class WithdrawConnectionAccessCommand
{
    public function __construct(
        public ConnectedIdentity $connectedIdentity,
    ) {}
}
