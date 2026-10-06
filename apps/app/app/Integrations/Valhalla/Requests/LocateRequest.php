<?php

declare(strict_types=1);

namespace App\Integrations\Valhalla\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Plugins\HasTimeout;

final class LocateRequest extends Request implements HasBody
{
    use HasJsonBody;
    use HasTimeout;

    protected Method $method = Method::POST;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 4;

    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly string $costing,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/locate';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'verbose' => true,
            'costing' => $this->costing,
            'locations' => [[
                'lat' => $this->latitude,
                'lon' => $this->longitude,
                'radius' => 80,
            ]],
        ];
    }
}
