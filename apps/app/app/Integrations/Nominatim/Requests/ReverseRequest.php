<?php

declare(strict_types=1);

namespace App\Integrations\Nominatim\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class ReverseRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/reverse';
    }

    /**
     * @return array<string, string|int|float>
     */
    protected function defaultQuery(): array
    {
        return [
            'lat' => $this->latitude,
            'lon' => $this->longitude,
            'format' => 'jsonv2',
            // A village or a quarter, not a street or a house.
            'zoom' => 14,
        ];
    }
}
