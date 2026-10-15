<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

final class SiteVerifyRequest extends Request implements HasBody
{
    use HasFormBody;

    #[Override]
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $secret,
        private readonly string $token,
        private readonly string $ip,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/siteverify';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'secret' => $this->secret,
            'response' => $this->token,
            'remoteip' => $this->ip,
        ];
    }
}
