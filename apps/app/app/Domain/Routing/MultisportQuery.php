<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use InvalidArgumentException;

/**
 * A brick, a duathlon, or a triathlon: an optional swim, then 1 to 4 land
 * legs that meet at the transition.
 */
final readonly class MultisportQuery
{
    public const int MOST_LAND_LEGS = 4;

    /**
     * @param  list<LandLeg>  $legs
     * @param  list<AvoidedStretch>  $avoid  The stretches every land leg skips.
     */
    public function __construct(
        public Layout $layout,
        public float $transitionLatitude,
        public float $transitionLongitude,
        public array $legs,
        public ?SwimLeg $swim = null,
        public array $avoid = [],
    ) {
        if ($this->transitionLatitude < -90 || $this->transitionLatitude > 90) {
            throw new InvalidArgumentException('Transition latitude is out of range.');
        }

        if ($this->transitionLongitude < -180 || $this->transitionLongitude > 180) {
            throw new InvalidArgumentException('Transition longitude is out of range.');
        }

        if ($this->legs === [] || count($this->legs) > self::MOST_LAND_LEGS) {
            throw new InvalidArgumentException('A multisport plan has 1 to 4 land legs.');
        }

        if ($this->layout === Layout::Chain) {
            foreach ($this->legs as $leg) {
                if ($leg->finish === null) {
                    throw new InvalidArgumentException('Every chained leg needs a finish.');
                }
            }
        }
    }

    /**
     * The same plan, skipping these stretches.
     *
     * @param  list<AvoidedStretch>  $avoid
     */
    public function withAvoid(array $avoid): self
    {
        return new self($this->layout, $this->transitionLatitude, $this->transitionLongitude, $this->legs, $this->swim, $avoid);
    }

    /**
     * The farthest the plan could take a rider from the transition: the
     * longest loop, or the whole chain, since each chained leg starts where
     * the last one ended.
     */
    public function reachMetres(): int
    {
        $lengths = array_map(fn (LandLeg $landLeg): int => $landLeg->distanceMetres, $this->legs);

        return $this->layout === Layout::Chain ? array_sum($lengths) : max($lengths);
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function transition(): array
    {
        return [$this->transitionLongitude, $this->transitionLatitude];
    }
}
