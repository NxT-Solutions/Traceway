<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\Email;
use App\Account\RiderName;
use SensitiveParameter;

final readonly class OpenAccountCommand
{
    public function __construct(
        public RiderName $name,
        public Email $email,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
