<?php

declare(strict_types=1);

namespace App\Integrations\Status\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * One cheap page of a service, such as its own status page, that answers when the service works.
 */
final class PingRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $path) {}

    public function resolveEndpoint(): string
    {
        return '/'.ltrim($this->path, '/');
    }
}
