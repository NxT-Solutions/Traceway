<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\DeviceProvider;

final readonly class StartDevicePairingCommand
{
    public function __construct(
        public DeviceProvider $provider,
    ) {}
}
