<?php

declare(strict_types=1);

namespace App\Commands;

use App\Routes\UnreadRoute;

final readonly class ReadRouteDetailsCommand
{
    public function __construct(public UnreadRoute $unreadRoute) {}
}
