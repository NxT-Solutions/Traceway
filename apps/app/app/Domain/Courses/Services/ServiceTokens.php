<?php

declare(strict_types=1);

namespace App\Domain\Courses\Services;

use SensitiveParameter;

/**
 * What a service hands back after the rider allows Traceway. Some services never expire their token.
 */
final readonly class ServiceTokens
{
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken = null,
        public ?int $expiresAt = null,
    ) {}

    /**
     * True when the token runs out within a minute of the given time, so it is refreshed before a send.
     */
    public function expiresBy(int $timestamp): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $timestamp + 60;
    }
}
