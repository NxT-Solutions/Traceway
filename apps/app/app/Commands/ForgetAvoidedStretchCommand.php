<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Routes\AvoidedStretchId;

final readonly class ForgetAvoidedStretchCommand
{
    public function __construct(
        public AvoidedStretchId $avoidedStretchId,
        public RiderId $riderId,
    ) {}
}
