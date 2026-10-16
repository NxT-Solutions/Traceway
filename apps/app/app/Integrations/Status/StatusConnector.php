<?php

declare(strict_types=1);

namespace App\Integrations\Status;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * One service the status page asks whether it works. It waits a little longer than the
 * planner does, so a slow answer counts as slow and not as down.
 */
final class StatusConnector extends Connector
{
    use HasTimeout;

    protected int $connectTimeout = 3;

    protected int $requestTimeout = 8;

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
            'User-Agent' => 'Traceway/1.0 status check (+https://traceway.online/status)',
        ];
    }
}
