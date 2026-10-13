<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * What the road under the wheels or the shoes is made of.
 */
enum RoadSurface: string
{
    case Paved = 'paved';
    case Gravel = 'gravel';
    case Dirt = 'dirt';
    case Unknown = 'unknown';

    /**
     * Valhalla names seven surfaces. A rider cares about three.
     */
    public static function fromValhalla(?string $surface): self
    {
        return match ($surface) {
            'paved_smooth', 'paved', 'paved_rough' => self::Paved,
            'compacted', 'gravel' => self::Gravel,
            'dirt', 'path', 'impassable' => self::Dirt,
            default => self::Unknown,
        };
    }
}
