<?php

declare(strict_types=1);

namespace App\Routes;

use InvalidArgumentException;

final readonly class CoordinateLine
{
    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private function __construct(public array $points) {}

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public static function ride(array $points): self
    {
        $line = self::optional($points);

        throw_if(! $line instanceof CoordinateLine || count($line->points) < 2, InvalidArgumentException::class, 'A route needs at least two points.');

        return $line;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public static function optional(array $points): ?self
    {
        if ($points === []) {
            return null;
        }

        foreach ($points as $point) {
            $longitude = $point[0];
            $latitude = $point[1];

            throw_if($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90, InvalidArgumentException::class, 'A coordinate is out of range.');
        }

        return new self($points);
    }
}
