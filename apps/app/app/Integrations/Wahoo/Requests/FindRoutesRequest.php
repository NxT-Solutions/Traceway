<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Looks up Traceway's earlier copy of a route by the id Traceway gave it.
 */
final class FindRoutesRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $externalId) {}

    public function resolveEndpoint(): string
    {
        return '/v1/routes';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['external_id' => $this->externalId];
    }
}
