<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum Shape: string
{
    case Loop = 'loop';
    case PointToPoint = 'point_to_point';
    case OutAndBack = 'out_and_back';
}
