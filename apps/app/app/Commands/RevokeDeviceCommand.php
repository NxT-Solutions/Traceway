<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\DeviceLinkId;
use App\Account\RiderId;

final readonly class RevokeDeviceCommand
{
    public function __construct(
        public RiderId $riderId,
        public DeviceLinkId $deviceLinkId,
    ) {}
}
