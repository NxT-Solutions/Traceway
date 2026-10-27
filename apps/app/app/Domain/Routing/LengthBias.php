<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * How the rider wants the length kept: as close as can be, never over it, or
 * never under it.
 */
enum LengthBias: string
{
    case Closest = 'closest';
    case AtMost = 'at_most';
    case AtLeast = 'at_least';
}
