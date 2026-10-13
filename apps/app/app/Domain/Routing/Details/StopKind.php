<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * A place to stop on the way.
 */
enum StopKind: string
{
    case Water = 'water';
    case Toilet = 'toilet';
    case Cafe = 'cafe';

    /**
     * An OpenStreetMap amenity as a stop, or null when it is not one.
     */
    public static function fromAmenity(?string $amenity): ?self
    {
        return match ($amenity) {
            'drinking_water', 'water_point' => self::Water,
            'toilets' => self::Toilet,
            'cafe' => self::Cafe,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Water => 'Water',
            self::Toilet => 'Toilet',
            self::Cafe => 'Café',
        };
    }
}
