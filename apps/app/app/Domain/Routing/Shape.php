<?php

declare(strict_types=1);

namespace App\Domain\Routing;

enum Shape: string
{
    case Loop = 'loop';
    case PointToPoint = 'point_to_point';
    case OutAndBack = 'out_and_back';

    /**
     * Read from a finished line, since a saved route does not keep its shape:
     * one way when it ends away from where it starts, out and back when it
     * rides about half of itself twice, a loop otherwise.
     *
     * @param  list<array{0: float, 1: float}>  $line
     */
    public static function of(array $line): self
    {
        $first = $line[0] ?? null;
        $last = $line[count($line) - 1] ?? null;

        if ($first === null || $last === null) {
            return self::Loop;
        }

        if (Distance::metres($first, $last) > 300.0) {
            return self::PointToPoint;
        }

        return RouteCleanliness::reverseShare($line) > 0.35 ? self::OutAndBack : self::Loop;
    }
}
