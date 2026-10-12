<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class SearchRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(private readonly string $search) {}

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultQuery(): array
    {
        return [
            'q' => $this->search,
            'format' => 'jsonv2',
            'limit' => 1,
        ];
    }
}
