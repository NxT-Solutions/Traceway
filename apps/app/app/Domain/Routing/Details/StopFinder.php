<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

use App\Domain\Routing\Sport;

/**
 * Finds water, toilets, and cafés a short way off a line.
 */
interface StopFinder
{
    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @return list<FoundStop>
     */
    public function along(Sport $sport, array $line): array;
}
