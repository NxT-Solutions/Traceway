<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * Used when no check is set up, in development and in tests. The forms keep
 * their rate limits.
 */
final readonly class NoHumanCheck implements HumanCheck
{
    public function passes(string $token, string $ip): bool
    {
        return true;
    }
}
