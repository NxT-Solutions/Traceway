<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/**
 * Ranks a generated line against the rider's filters.
 * Higher is better. The geometry itself always comes from the routing engine.
 */
final class CandidateScorer
{
    public function score(ScoreInput $input): float
    {
        $target = max(1, $input->targetDistanceMetres);
        $distanceError = abs($input->distanceMetres - $input->targetDistanceMetres) / $target;
        $distanceScore = max(0.0, 1 - $distanceError);

        $surfaceScore = match ($input->surface) {
            Surface::Paved => $input->pavedRatio,
            Surface::Dirt => max(0.0, 1 - $input->pavedRatio - $input->unknownSurfaceRatio),
            Surface::Mixed => 1.0,
        };

        $kilometres = max(0.1, $input->distanceMetres / 1000);
        $ascentPerKilometre = $input->ascentMetres / $kilometres;
        $elevationScore = match ($input->elevation) {
            ElevationBias::Minimize => max(0.0, 1 - ($ascentPerKilometre / 40)),
            ElevationBias::Neutral => 1.0,
            ElevationBias::Maximize => min(1.0, $ascentPerKilometre / 40),
        };

        $score = ($distanceScore * 40)
            + ($surfaceScore * 25)
            + ($elevationScore * 20)
            + (max(0.0, min(1.0, $input->cyclewayRatio)) * 10)
            - ($input->unknownSurfaceRatio * 10);

        if ($input->usesTrunk) {
            $score -= 25;
        }

        if ($input->usesFerry) {
            $score -= 25;
        }

        return round($score, 2);
    }
}
