<?php

declare(strict_types=1);

namespace App\Integrations\GarminConnect\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Ends the rider's registration with Traceway at Garmin. Garmin asks for this whenever a rider disconnects or
 * deletes their account.
 */
final class DeleteRegistrationRequest extends Request
{
    #[Override]
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return '/wellness-api/rest/user/registration';
    }
}
