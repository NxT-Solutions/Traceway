<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

final readonly class FinishTourCommand
{
    public function __construct(public RiderId $riderId) {}
}
