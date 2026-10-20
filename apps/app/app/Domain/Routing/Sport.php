<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum Sport: string
{
    case Road = 'road';
    case Gravel = 'gravel';
    case Mtb = 'mtb';
    case Run = 'run';
    case TrailRun = 'trail_run';
    case Walk = 'walk';
    case Hike = 'hike';

    /**
     * The ground the sport asks for when the rider does not choose. The
     * planner and the AI connector both start from it.
     */
    public function defaultSurface(): Surface
    {
        return match ($this) {
            self::Road, self::Run => Surface::Paved,
            self::Gravel, self::Walk => Surface::Mixed,
            self::Mtb, self::TrailRun, self::Hike => Surface::Dirt,
        };
    }

    /**
     * Each sport's default surface, by value, for the planner.
     *
     * @return array<string, string>
     */
    public static function defaultSurfaces(): array
    {
        $surfaces = [];

        foreach (self::cases() as $sport) {
            $surfaces[$sport->value] = $sport->defaultSurface()->value;
        }

        return $surfaces;
    }
}
