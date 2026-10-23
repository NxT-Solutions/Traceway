<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * The device unlinks itself. The token arrives hashed.
 */
final readonly class UnlinkDeviceCommand
{
    public function __construct(
        public string $tokenHash,
    ) {}
}
