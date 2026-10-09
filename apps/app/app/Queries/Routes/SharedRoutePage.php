<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'SharedRoutePage',
    required: ['route'],
    properties: [
        new Property(property: 'route', ref: '#/components/schemas/SharedRoute'),
    ],
)]
final readonly class SharedRoutePage
{
    public function __construct(public SharedRoute $route) {}

    /**
     * @return array{route: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'route' => $this->route->toArray(),
        ];
    }
}
