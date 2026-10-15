<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The upload task, pending until the route is read, then completed with the new route in its items.
 */
final class GetTaskRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $id) {}

    public function resolveEndpoint(): string
    {
        return '/api/v1/tasks/'.rawurlencode($this->id).'.json';
    }
}
