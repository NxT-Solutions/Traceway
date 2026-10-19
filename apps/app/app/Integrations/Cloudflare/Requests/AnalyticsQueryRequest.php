<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * One GraphQL query to the Analytics API.
 */
final class AnalyticsQueryRequest extends Request implements HasBody
{
    use HasJsonBody;

    #[Override]
    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        private readonly string $graphql,
        private readonly array $variables,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/graphql';
    }

    /**
     * @return array{query: string, variables: array<string, mixed>}
     */
    protected function defaultBody(): array
    {
        return [
            'query' => $this->graphql,
            'variables' => $this->variables,
        ];
    }
}
