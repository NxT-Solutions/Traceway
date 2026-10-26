<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum RouteChangeKind: string
{
    case Move = 'move';
    case Insert = 'insert';
    case Remove = 'remove';
    case Reroute = 'reroute';
}
