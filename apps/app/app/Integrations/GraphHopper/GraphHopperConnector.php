<?php

declare(strict_types=1);

namespace App\Integrations\GraphHopper;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

final class GraphHopperConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 12;

    public function __construct(private readonly string $baseUrl) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
