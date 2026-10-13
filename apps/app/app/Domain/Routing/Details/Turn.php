<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * A turn the watch warns about before it comes.
 */
enum Turn: string
{
    case Left = 'left';
    case Right = 'right';
    case SlightLeft = 'slight_left';
    case SlightRight = 'slight_right';
    case SharpLeft = 'sharp_left';
    case SharpRight = 'sharp_right';
    case UTurn = 'u_turn';
    case Straight = 'straight';

    /**
     * A Valhalla maneuver type as a turn, or null when the road just goes on.
     */
    public static function fromValhalla(int $type): ?self
    {
        return match ($type) {
            9, 18, 20, 23, 37 => self::SlightRight,
            10 => self::Right,
            11 => self::SharpRight,
            12, 13 => self::UTurn,
            14 => self::SharpLeft,
            15 => self::Left,
            16, 19, 21, 24, 38 => self::SlightLeft,
            26 => self::Straight,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Right => 'Right',
            self::SlightLeft => 'Bear left',
            self::SlightRight => 'Bear right',
            self::SharpLeft => 'Sharp left',
            self::SharpRight => 'Sharp right',
            self::UTurn => 'U-turn',
            self::Straight => 'Roundabout',
        };
    }
}
