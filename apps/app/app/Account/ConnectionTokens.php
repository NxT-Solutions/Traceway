<?php

declare(strict_types=1);

namespace App\Account;

use SensitiveParameter;

/**
 * The keys a connected service gave Traceway. They are encrypted at rest and never leave the server. The expiry is
 * a Unix time, not a number of seconds.
 */
final readonly class ConnectionTokens
{
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken = null,
        public ?int $expiresAt = null,
    ) {}
}
