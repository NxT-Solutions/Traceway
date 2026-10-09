<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;

final readonly class UpdateProfileCommand
{
    public function __construct(
        public RiderId $riderId,
        public RiderName $name,
        public Email $email,
    ) {}
}
