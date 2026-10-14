<?php

declare(strict_types=1);

namespace App\Integrations\OpenFreeMap\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class TileRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * The tiles are cached for years, and a second try usually lands. A pool
     * sends each request once; these apply when it is sent on its own.
     */
    #[Override]
    public ?int $tries = 2;

    #[Override]
    public ?int $retryInterval = 250;

    #[Override]
    public ?bool $throwOnMaxTries = false;

    /**
     * @param  string  $path  The build's tile path, with {z}, {x}, and {y} in it.
     */
    public function __construct(
        private readonly string $path,
        public readonly int $zoom,
        public readonly int $x,
        public readonly int $y,
    ) {}

    public function resolveEndpoint(): string
    {
        return strtr($this->path, [
            '{z}' => (string) $this->zoom,
            '{x}' => (string) $this->x,
            '{y}' => (string) $this->y,
        ]);
    }
}
