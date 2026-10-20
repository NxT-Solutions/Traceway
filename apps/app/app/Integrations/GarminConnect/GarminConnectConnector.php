<?php

declare(strict_types=1);

namespace App\Integrations\GarminConnect;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Garmin Connect's partner API, for what Traceway does with a rider's connection there.
 */
final class GarminConnectConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 5;

    protected int $requestTimeout = 20;

    public function __construct(private readonly string $baseUrl = 'https://apis.garmin.com') {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
