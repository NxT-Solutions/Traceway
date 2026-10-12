<?php

declare(strict_types=1);

namespace App\Domain\Routing;

final readonly class MultisportRoute
{
    /**
     * @param  list<PlannedLeg>  $legs
     */
    public function __construct(public Layout $layout, public array $legs) {}

    public function distanceMetres(): int
    {
        return array_sum(array_map(fn (PlannedLeg $plannedLeg): int => $plannedLeg->distanceMetres, $this->legs));
    }

    public function ascentMetres(): int
    {
        return array_sum(array_map(fn (PlannedLeg $plannedLeg): int => $plannedLeg->ascentMetres, $this->legs));
    }
}
