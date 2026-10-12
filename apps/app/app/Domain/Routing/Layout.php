<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * How the legs of a multisport plan meet. Loops all start and end at the
 * transition. A chain hands each leg over where the last one finished.
 */
enum Layout: string
{
    case Loops = 'loops';
    case Chain = 'chain';
}
