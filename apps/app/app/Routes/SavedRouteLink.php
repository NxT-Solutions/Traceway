<?php

declare(strict_types=1);

namespace App\Routes;

final readonly class SavedRouteLink
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
