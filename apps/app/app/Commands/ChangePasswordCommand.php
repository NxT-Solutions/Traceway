<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use SensitiveParameter;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public RiderId $riderId,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
