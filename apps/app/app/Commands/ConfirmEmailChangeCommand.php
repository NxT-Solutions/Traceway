<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\Email;
use App\Account\RiderId;

/**
 * The rider opened the link sent to the new email they asked for.
 */
final readonly class ConfirmEmailChangeCommand
{
    public function __construct(
        public RiderId $riderId,
        public Email $email,
    ) {}
}
