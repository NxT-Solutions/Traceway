<?php

declare(strict_types=1);

namespace App\Integrations\GraphHopper;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

final class GraphHopperConnector extends Connector
{
    use AcceptsJson;

    public function __construct(private readonly string $baseUrl) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
