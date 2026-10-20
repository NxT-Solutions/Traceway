<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectionProvider;
use App\Account\Email;
use App\Account\RiderName;
use SensitiveParameter;

/**
 * Emails a new rider the link that opens their account, once they show the address is theirs. The token is the
 * one their waiting sign-up is kept under.
 */
final readonly class SendConnectionSignUpLinkCommand
{
    public function __construct(
        public RiderName $name,
        public Email $email,
        public ConnectionProvider $connectionProvider,
        #[SensitiveParameter]
        public string $token,
    ) {}
}
