<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * One change to a drawn route: a point moved, put in, or taken out, or some
 * legs planned again as they are. Only the legs it touches are planned again.
 */
final readonly class RouteChange
{
    /**
     * @param  array{0: float, 1: float}|null  $point  Where a moved or new point goes.
     * @param  list<int>  $legs  The legs to plan again, for a reroute.
     */
    private function __construct(
        public RouteChangeKind $kind,
        public int $index = 0,
        public ?array $point = null,
        public array $legs = [],
    ) {}

    /**
     * @param  array{0: float, 1: float}  $point
     */
    public static function move(int $index, array $point): self
    {
        return new self(RouteChangeKind::Move, $index, $point);
    }

    /**
     * A new point that becomes the waypoint at this index.
     *
     * @param  array{0: float, 1: float}  $point
     */
    public static function insert(int $index, array $point): self
    {
        return new self(RouteChangeKind::Insert, $index, $point);
    }

    public static function remove(int $index): self
    {
        return new self(RouteChangeKind::Remove, $index);
    }

    /**
     * @param  list<int>  $legs
     */
    public static function reroute(array $legs): self
    {
        throw_if($legs === [], InvalidArgumentException::class, 'A reroute needs a leg.');

        return new self(RouteChangeKind::Reroute, legs: array_values(array_unique($legs)));
    }
}
