<?php

declare(strict_types=1);

namespace App\Commands;

use App\Account\ConnectionProvider;

/**
 * Refreshes the keys every rider's connection to the service keeps, so they do not run out while the rider logs
 * in some other way, and Traceway can still remove its access when they disconnect.
 */
final readonly class KeepConnectionAccessCommand
{
    public function __construct(public ConnectionProvider $connectionProvider) {}
}
