<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

use App\Domain\Routing\Sport;

/**
 * Reads the heights, the surfaces, and the turns along a finished line.
 */
interface RoadDescriber
{
    /**
     * @param  list<array{0: float, 1: float}>  $line
     */
    public function describe(Sport $sport, array $line): RoadDescription;
}
