<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The account the key belongs to, as {"user": {...}}.
 */
final class GetCurrentUserRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/v1/users/current.json';
    }
}
