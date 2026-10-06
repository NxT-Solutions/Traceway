<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum Sport: string
{
    case Road = 'road';
    case Gravel = 'gravel';
    case Mtb = 'mtb';
    case Run = 'run';
    case TrailRun = 'trail_run';
    case Walk = 'walk';
    case Hike = 'hike';
}
