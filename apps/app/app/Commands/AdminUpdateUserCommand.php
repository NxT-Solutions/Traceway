<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;

/**
 * Changes a rider's name or email from the admin area, and can mark the email as confirmed.
 */
final readonly class AdminUpdateUserCommand
{
    public function __construct(
        public RiderId $actorId,
        public RiderId $riderId,
        public RiderName $name,
        public Email $email,
        public bool $confirmEmail,
    ) {}
}
