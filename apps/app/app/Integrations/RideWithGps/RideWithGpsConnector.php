<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Ride with GPS. A route stored here reaches a Karoo, a Bryton, or a Wahoo once the rider sends it on with Ride with GPS's Send to Device.
 * Its tokens do not expire, and it has no refresh token. The rider's sign-in runs through Socialite; this
 * connector only uses and revokes the key it gave.
 */
final class RideWithGpsConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 30;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://ridewithgps.com',
    ) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return $this->apiKey === null || $this->apiKey === '' ? [] : ['x-rwgps-api-key' => $this->apiKey];
    }
}
