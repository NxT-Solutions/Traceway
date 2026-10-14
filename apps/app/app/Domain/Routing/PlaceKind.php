<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * What a found place is, so the planner can show it with the right mark.
 */
enum PlaceKind: string
{
    case Address = 'address';
    case Street = 'street';
    case Town = 'town';
    case Place = 'place';
}
