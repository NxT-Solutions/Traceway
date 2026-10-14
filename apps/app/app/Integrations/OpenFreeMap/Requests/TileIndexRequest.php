<?php

declare(strict_types=1);

namespace App\Integrations\OpenFreeMap\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The TileJSON that names the current build of the tiles. Each build lives
 * under its own path, so the tile addresses come from here.
 */
final class TileIndexRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    #[Override]
    public ?int $tries = 2;

    #[Override]
    public ?int $retryInterval = 250;

    #[Override]
    public ?bool $throwOnMaxTries = false;

    public function resolveEndpoint(): string
    {
        return '/planet';
    }
}
