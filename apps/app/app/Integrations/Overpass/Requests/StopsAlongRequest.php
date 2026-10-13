<?php

declare(strict_types=1);

namespace App\Integrations\Overpass\Requests;

use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Water, toilets, and cafés inside a row of small boxes that follow a line.
 * A box query reads the server's index, so it answers in about a second,
 * where a search around the line itself can take twenty. Stops in a box
 * corner, off the line, are dropped by the caller.
 */
final class StopsAlongRequest extends Request implements HasBody
{
    use HasFormBody;

    #[Override]
    protected Method $method = Method::POST;

    /** The public server turns a busy moment away. A second try usually lands. */
    #[Override]
    public ?int $tries = 2;

    #[Override]
    public ?int $retryInterval = 750;

    #[Override]
    public ?bool $throwOnMaxTries = false;

    /**
     * @param  list<array{south: float, west: float, north: float, east: float}>  $boxes
     */
    public function __construct(private readonly array $boxes) {}

    public function resolveEndpoint(): string
    {
        return '/interpreter';
    }

    public function overpassQuery(): string
    {
        $statements = implode('', array_map(
            fn (array $box): string => 'node["amenity"~"^(drinking_water|water_point|toilets|cafe)$"]('
                .round($box['south'], 5).','.round($box['west'], 5).','.round($box['north'], 5).','.round($box['east'], 5).');',
            $this->boxes,
        ));

        return '[out:json][timeout:10];('.$statements.');out body 2000;';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['data' => $this->overpassQuery()];
    }
}
