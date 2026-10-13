<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * The turns along a line the rider already chose.
 */
final class TraceRouteRequest extends Request implements HasBody
{
    use HasJsonBody;

    #[Override]
    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function resolveEndpoint(): string
    {
        return '/trace_route';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload;
    }
}
