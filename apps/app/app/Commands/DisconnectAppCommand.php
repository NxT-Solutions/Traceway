<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

final readonly class DisconnectAppCommand
{
    public function __construct(
        public RiderId $riderId,
        public string $clientId,
    ) {}
}
