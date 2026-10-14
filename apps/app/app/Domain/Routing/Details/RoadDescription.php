<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * What the road along a line is like: its heights, its surfaces, and its turns.
 */
final readonly class RoadDescription
{
    /**
     * @param  list<ProfilePoint>  $profile
     * @param  list<SurfaceShare>  $surfaces
     * @param  list<Cue>  $cues
     */
    public function __construct(
        public array $profile,
        public array $surfaces,
        public array $cues,
        // False when a part of the line got no heights, surfaces, or turns.
        public bool $complete = true,
    ) {}
}
