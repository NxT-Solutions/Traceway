<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * OpenStreetMap's place search. The public server asks every client to name
 * itself and to stay under one request a second.
 */
final class NominatimConnector extends Connector
{
    use AcceptsJson;
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 6;

    public function __construct(private readonly string $baseUrl) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => 'Traceway/1.0 (+https://traceway.online)',
        ];
    }
}
