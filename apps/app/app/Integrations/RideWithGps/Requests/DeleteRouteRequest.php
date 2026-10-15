<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Removes the copy Traceway sent before. The API cannot replace a route's track, so a new send uploads afresh.
 */
final class DeleteRouteRequest extends Request
{
    #[Override]
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $id) {}

    public function resolveEndpoint(): string
    {
        return '/api/v1/routes/'.rawurlencode($this->id).'.json';
    }
}
