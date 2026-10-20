<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * The rider's Wahoo account: its number, and the email when the rider allowed Traceway to read it.
 */
final class GetUserRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/v1/user';
    }
}
