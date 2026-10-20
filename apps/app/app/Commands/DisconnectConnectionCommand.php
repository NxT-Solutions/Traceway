<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectionProvider;
use App\Account\RiderId;

final readonly class DisconnectConnectionCommand
{
    public function __construct(
        public RiderId $riderId,
        public ConnectionProvider $connectionProvider,
    ) {}
}
