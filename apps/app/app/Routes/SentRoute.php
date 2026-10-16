<?php

declare(strict_types=1);

namespace App\Routes;

/**
 * What came of sending a route to a service: there now, or still being read by the service.
 */
final readonly class SentRoute
{
    public function __construct(
        public bool $processing,
        public ?string $url = null,
    ) {}
}
