<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * A point a followed line passes, and the way the rider is going there. A
 * point on a road with a lane each way is only met going the right way, so
 * the line does not ride past it, turn, and come back.
 */
final readonly class FollowPoint
{
    /**
     * @param  array{0: float, 1: float}  $point
     * @param  int|null  $heading  Degrees from north, or null for a point the rider may leave any way, such as home.
     */
    public function __construct(
        public array $point,
        public ?int $heading = null,
    ) {}
}
