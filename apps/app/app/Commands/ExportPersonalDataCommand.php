<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;

final readonly class ExportPersonalDataCommand
{
    public function __construct(public RiderId $riderId) {}
}
