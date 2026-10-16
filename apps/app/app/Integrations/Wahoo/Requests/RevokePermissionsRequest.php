<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Withdraws every permission the rider gave Traceway at Wahoo.
 */
final class RevokePermissionsRequest extends Request
{
    #[Override]
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return '/v1/permissions';
    }
}
