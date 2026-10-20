<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Account\RiderName;

/**
 * Opens an account for a rider who used the emailed sign-up link in another browser than the one that asked for
 * it. The link proves the email is theirs, not that they came from the service, so the service is not connected.
 * The service is named for the message when the email got an account in the meantime.
 */
final readonly class OpenAccountWithoutConnectionCommand
{
    public function __construct(
        public ConnectionProvider $connectionProvider,
        public RiderName $name,
        public Email $email,
    ) {}
}
