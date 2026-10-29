<?php

declare(strict_types=1);

namespace App\Integrations\Osrm\Requests;

use Override;
use Saloon\Enums\Method;
use Saloon\Http\Request;

final class CyclingRouteRequest extends Request
{
    #[Override]
    protected Method $method = Method::GET;

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     * @param  bool  $serverPerProfile  The profile picks the server's path, such as /routed-bike, rather than the route's.
     */
    public function __construct(
        private readonly array $coordinates,
        private readonly string $profile = 'bike',
        private readonly bool $alternatives = false,
        private readonly bool $serverPerProfile = false,
    ) {}

    public function resolveEndpoint(): string
    {
        $path = implode(';', array_map(
            fn (array $point): string => $point[0].','.$point[1],
            $this->coordinates,
        ));

        return $this->serverPerProfile
            ? '/routed-'.$this->profile.'/route/v1/driving/'.$path
            : '/route/v1/'.$this->profile.'/'.$path;
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
            'alternatives' => $this->alternatives ? 'true' : 'false',
        ];
    }
}
