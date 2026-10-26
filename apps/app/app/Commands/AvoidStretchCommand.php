<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\RiderId;
use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

final readonly class AvoidStretchCommand
{
    public function __construct(
        public RiderId $riderId,
        public float $latitude,
        public float $longitude,
        public Sport $sport,
        public Surface $surface,
    ) {}
}
