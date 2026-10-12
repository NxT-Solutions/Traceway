<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Routing\Sport;
use App\Domain\Routing\Surface;

/**
 * The ground each sport asks for when the person does not say.
 */
final class SportDefaults
{
    public static function surface(Sport $sport): Surface
    {
        return match ($sport) {
            Sport::Road, Sport::Run => Surface::Paved,
            Sport::Gravel, Sport::Walk => Surface::Mixed,
            Sport::Mtb, Sport::TrailRun, Sport::Hike => Surface::Dirt,
        };
    }

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
