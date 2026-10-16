<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Adds a route to the rider's Wahoo library. The FIT file goes in as a base64 data URI.
 */
final class CreateRouteRequest extends Request implements HasBody
{
    use HasFormBody;

    #[Override]
    protected Method $method = Method::POST;

    /**
     * @param  array<string, string|int|float>  $route
     */
    public function __construct(private readonly array $route) {}

    public function resolveEndpoint(): string
    {
        return '/v1/routes';
    }

    /**
     * @return array<string, string|int|float>
     */
    protected function defaultBody(): array
    {
        $body = [];

        foreach ($this->route as $field => $value) {
            $body['route['.$field.']'] = $value;
        }

        return $body;
    }
}
