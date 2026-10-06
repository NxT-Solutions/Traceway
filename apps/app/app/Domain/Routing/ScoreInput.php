<?php

declare(strict_types=1);

namespace App\Domain\Routing;

final readonly class ScoreInput
{
    public function __construct(
        public int $targetDistanceMetres,
        public int $distanceMetres,
        public Surface $surface,
        public float $pavedRatio,
        public float $unknownSurfaceRatio,
        public int $ascentMetres,
        public ElevationBias $elevation,
        public float $cyclewayRatio,
        public bool $usesTrunk,
        public bool $usesFerry,
    ) {}
}
