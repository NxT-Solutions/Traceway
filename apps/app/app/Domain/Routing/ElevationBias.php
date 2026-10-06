<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum ElevationBias: string
{
    case Minimize = 'minimize';
    case Neutral = 'neutral';
    case Maximize = 'maximize';
}
