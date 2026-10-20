<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectedIdentity;
use App\Account\Email;
use App\Account\RiderName;

/**
 * Opens an account for a new rider who came from a connected service. The name and email are the ones the rider
 * confirmed or typed, which can differ from what the service sent. The email is confirmed when the rider used
 * the link Traceway sent to it.
 */
final readonly class OpenAccountWithConnectionCommand
{
    public function __construct(
        public ConnectedIdentity $connectedIdentity,
        public RiderName $name,
        public Email $email,
        public bool $emailConfirmed = false,
    ) {}
}
