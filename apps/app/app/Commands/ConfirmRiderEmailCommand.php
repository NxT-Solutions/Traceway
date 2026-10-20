<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

/**
 * A connected service proved the rider reads their account's email, as the confirmation link would.
 */
final readonly class ConfirmRiderEmailCommand
{
    public function __construct(
        public RiderId $riderId,
    ) {}
}
