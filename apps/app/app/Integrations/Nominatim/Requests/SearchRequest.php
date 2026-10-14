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

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $viewbox  West, south, east, north. Places inside rank first, places outside still count.
     */
    public function __construct(
        private readonly string $search,
        private readonly int $limit = 1,
        private readonly ?array $viewbox = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /**
     * @return array<string, string|int>
     */
    protected function defaultQuery(): array
    {
        $query = [
            'q' => $this->search,
            'format' => 'jsonv2',
            'limit' => $this->limit,
        ];

        if ($this->viewbox !== null) {
            $query['viewbox'] = implode(',', array_map(static fn (float $edge): string => sprintf('%.4F', $edge), $this->viewbox));
            $query['bounded'] = 0;
            $query['addressdetails'] = 1;
        }

        return $query;
    }
}
