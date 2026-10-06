<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

final class ValhallaConnector extends Connector
{
    use AcceptsJson;

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
            'User-Agent' => 'Traceway/0.1 (route concept)',
        ];
    }
}
