<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;

/**
 * Opens an account for someone, who chooses their password from an email.
 */
final readonly class AdminCreateUserCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderName $name,
        public Email $email,
    ) {}
}
