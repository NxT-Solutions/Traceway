<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Cloudflare's GraphQL Analytics API, for the totals of requests and visits on the admin area's Traffic page.
 * The token needs only Analytics Read on the account and the zone.
 */
final class CloudflareAnalyticsConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 10;

    public function __construct(private readonly string $token) {}

    public function resolveBaseUrl(): string
    {
        return 'https://api.cloudflare.com/client/v4';
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->token);
    }
}
