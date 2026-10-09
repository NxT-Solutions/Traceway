<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Ranks a generated line against the rider's filters.
 * Higher is better. The geometry itself always comes from the routing engine.
 */
final class CandidateScorer
{
    public function score(ScoreInput $scoreInput): float
    {
        $target = max(1, $scoreInput->targetDistanceMetres);
        $distanceError = abs($scoreInput->distanceMetres - $scoreInput->targetDistanceMetres) / $target;
        $distanceScore = max(0.0, 1 - $distanceError);

        $surfaceScore = match ($scoreInput->surface) {
            Surface::Paved => $scoreInput->pavedRatio,
            Surface::Dirt => max(0.0, 1 - $scoreInput->pavedRatio - $scoreInput->unknownSurfaceRatio),
            Surface::Mixed => 1.0,
        };

        $kilometres = max(0.1, $scoreInput->distanceMetres / 1000);
        $ascentPerKilometre = $scoreInput->ascentMetres / $kilometres;
        $elevationScore = match ($scoreInput->elevation) {
            ElevationBias::Minimize => max(0.0, 1 - ($ascentPerKilometre / 40)),
            ElevationBias::Neutral => 1.0,
            ElevationBias::Maximize => min(1.0, $ascentPerKilometre / 40),
        };

        $score = ($distanceScore * 40)
            + ($surfaceScore * 25)
            + ($elevationScore * 20)
            + (max(0.0, min(1.0, $scoreInput->cyclewayRatio)) * 10)
            - ($scoreInput->unknownSurfaceRatio * 10);

        if ($scoreInput->usesTrunk) {
            $score -= 25;
        }

        if ($scoreInput->usesFerry) {
            $score -= 25;
        }

        return round($score, 2);
    }
}
