<?php

declare(strict_types=1);

namespace App\Integrations\Wahoo\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Replaces a route Traceway sent before, so the rider's library keeps one copy.
 */
final class UpdateRouteRequest extends Request implements HasBody
{
    use HasFormBody;

    #[Override]
    protected Method $method = Method::PUT;

    /**
     * @param  array<string, string|int|float>  $route
     */
    public function __construct(private readonly string $id, private readonly array $route) {}

    public function resolveEndpoint(): string
    {
        return '/v1/routes/'.rawurlencode($this->id);
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
