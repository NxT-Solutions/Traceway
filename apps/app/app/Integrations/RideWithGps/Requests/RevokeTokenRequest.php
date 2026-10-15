<?php

declare(strict_types=1);

namespace App\Integrations\RideWithGps\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

/**
 * Makes the rider's token stop working.
 */
final class RevokeTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    #[Override]
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        #[SensitiveParameter] private readonly string $token,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/oauth/revoke.json';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $this->token,
        ];
    }
}
