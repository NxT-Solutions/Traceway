<?php

declare(strict_types=1);

use App\Domain\Routing\CandidateScorer;
use App\Domain\Routing\ElevationBias;
use App\Domain\Routing\ScoreInput;
use App\Domain\Routing\Surface;

/**
 * @param  array<string, mixed>  $overrides
 */
function scoreInput(array $overrides = []): ScoreInput
{
    return new ScoreInput(
        targetDistanceMetres: $overrides['targetDistanceMetres'] ?? 40_000,
        distanceMetres: $overrides['distanceMetres'] ?? 40_000,
        surface: $overrides['surface'] ?? Surface::Paved,
        pavedRatio: $overrides['pavedRatio'] ?? 1.0,
        unknownSurfaceRatio: $overrides['unknownSurfaceRatio'] ?? 0.0,
        ascentMetres: $overrides['ascentMetres'] ?? 100,
        elevation: $overrides['elevation'] ?? ElevationBias::Neutral,
        cyclewayRatio: $overrides['cyclewayRatio'] ?? 0.0,
        usesTrunk: $overrides['usesTrunk'] ?? false,
        usesFerry: $overrides['usesFerry'] ?? false,
    );
}

it('ranks an exact paved match above a short gravel detour', function (): void {
    $scorer = new CandidateScorer;

    $exact = $scorer->score(scoreInput());
    $shortDirt = $scorer->score(scoreInput([
        'distanceMetres' => 20_000,
        'surface' => Surface::Paved,
        'pavedRatio' => 0.2,
    ]));

    expect($exact)->toBeGreaterThan($shortDirt);
});

it('penalises a trunk road and a ferry', function (): void {
    $scorer = new CandidateScorer;
    $clean = $scorer->score(scoreInput());

    expect($scorer->score(scoreInput(['usesTrunk' => true])))->toBe($clean - 25.0)
        ->and($scorer->score(scoreInput(['usesFerry' => true])))->toBe($clean - 25.0);
});

it('prefers less climb when the rider asked for flat', function (): void {
    $scorer = new CandidateScorer;

    $flat = $scorer->score(scoreInput([
        'elevation' => ElevationBias::Minimize,
        'ascentMetres' => 80,
    ]));
    $hilly = $scorer->score(scoreInput([
        'elevation' => ElevationBias::Minimize,
        'ascentMetres' => 1_200,
    ]));

    expect($flat)->toBeGreaterThan($hilly);
});

it('rewards unknown surface less than a known paved line', function (): void {
    $scorer = new CandidateScorer;

    $known = $scorer->score(scoreInput());
    $unknown = $scorer->score(scoreInput([
        'pavedRatio' => 0.4,
        'unknownSurfaceRatio' => 0.6,
    ]));

    expect($known)->toBeGreaterThan($unknown);
});
