<?php

declare(strict_types=1);

namespace App\Routes;

use App\Domain\Routing\Sport;

/**
 * A saved route whose climb, surfaces, turns, and stops are not read yet.
 */
final readonly class UnreadRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<SavedLeg>  $legs
     */
    public function __construct(
        public RouteId $routeId,
        public Sport $sport,
        public array $line,
        public array $legs,
    ) {}
}
