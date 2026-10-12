<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\DeviceLabel;
use App\Account\PairingCode;
use App\Account\RiderId;

final readonly class LinkDeviceCommand
{
    public function __construct(
        public RiderId $riderId,
        public PairingCode $code,
        public DeviceLabel $label,
    ) {}
}
