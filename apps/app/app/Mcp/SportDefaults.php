<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Routing\Sport;

/**
 * How the connector names each sport in a reply. The ground a sport asks
 * for is Sport::defaultSurface(), shared with the planner.
 */
final class SportDefaults
{
    public static function label(Sport $sport): string
    {
        return match ($sport) {
            Sport::Road => 'road ride',
            Sport::Gravel => 'gravel ride',
            Sport::Mtb => 'mountain-bike ride',
            Sport::Run => 'run',
            Sport::TrailRun => 'trail run',
            Sport::Walk => 'walk',
            Sport::Hike => 'hike',
        };
    }
}
