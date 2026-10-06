<?php

declare(strict_types=1);

namespace App\Integrations\Osrm\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class CyclingRouteRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        private readonly array $coordinates,
        private readonly string $profile = 'bike',
    ) {}

    public function resolveEndpoint(): string
    {
        $path = implode(';', array_map(
            fn (array $point): string => $point[0].','.$point[1],
            $this->coordinates,
        ));

        return '/route/v1/'.$this->profile.'/'.$path;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'false',
            'continue_straight' => 'true',
        ];
    }
}
