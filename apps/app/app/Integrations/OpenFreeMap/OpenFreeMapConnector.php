<?php

declare(strict_types=1);

namespace App\Integrations\OpenFreeMap;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * OpenFreeMap's map tiles, from OpenStreetMap: the ones the planner's map
 * draws. The server reads the water, toilets, and cafés out of them.
 */
final class OpenFreeMapConnector extends Connector
{
    use HasTimeout;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 6;

    public function __construct(private readonly string $baseUrl) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * The tiles come as they are, not packed for the trip, so nothing is
     * unpacked without a limit. The tile reader unpacks with one.
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return ['decode_content' => false];
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
