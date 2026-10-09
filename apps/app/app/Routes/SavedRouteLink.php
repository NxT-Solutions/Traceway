<?php

declare(strict_types=1);

namespace App\Routes;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'SavedRouteLink',
    required: ['id', 'url'],
    properties: [
        new Property(property: 'id', type: 'string', format: 'uuid'),
        new Property(property: 'url', type: 'string'),
    ],
)]
final readonly class SavedRouteLink
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}

    /**
     * @return array{id: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
        ];
    }
}
