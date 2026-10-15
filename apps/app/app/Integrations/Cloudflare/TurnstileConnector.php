<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Cloudflare Turnstile, the free check under the sign-up form that tells a
 * person from a script.
 */
final class TurnstileConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 5;

    public function resolveBaseUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0';
    }
}
