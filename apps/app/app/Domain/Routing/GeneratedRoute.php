<?php

declare(strict_types=1);

namespace App\Domain\Routing;

final readonly class GeneratedRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    public function __construct(
        public RouteQuery $query,
        public array $coordinates,
        public int $distanceMetres,
        public int $ascentMetres,
    ) {}
}
