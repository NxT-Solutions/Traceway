<?php

declare(strict_types=1);

namespace App\Account;

use SensitiveParameter;

/**
 * The tokens Traceway keeps for a rider's route service. They are encrypted at rest and never leave the server.
 */
final readonly class RouteServiceTokens
{
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken = null,
        public ?int $expiresAt = null,
    ) {}
}
