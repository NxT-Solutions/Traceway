<?php

declare(strict_types=1);

namespace App\Domain\Connections;

/**
 * What one pass over many riders' connections did: how many it brought up to date, how many the service turned
 * down so the rider has to connect again, how many it could not reach, and how many it left alone.
 */
final readonly class ConnectionTally
{
    public function __construct(
        public int $done = 0,
        public int $refused = 0,
        public int $unreachable = 0,
        public int $skipped = 0,
    ) {}

    public function total(): int
    {
        return $this->done + $this->refused + $this->unreachable + $this->skipped;
    }
}
