<?php

declare(strict_types=1);

namespace App\Domain\Routing\Details;

/**
 * Totals the climbing and the descending of a height profile. A height
 * model wobbles by a metre or two between samples, so a change only counts
 * once it passes a few metres. Without that, a flat road adds up to a hill.
 */
final class Climb
{
    public const float THRESHOLD_METRES = 3.0;

    /** Heights are averaged over this stretch before they are added up. */
    public const float SMOOTHING_METRES = 150.0;

    /**
     * The profile with each height averaged over the stretch around it. A
     * height model reads a bridge, a building, or a cutting as the ground,
     * and those spikes are not hills a rider climbs.
     *
     * @param  list<ProfilePoint>  $profile
     * @return list<ProfilePoint>
     */
    public static function smooth(array $profile, float $windowMetres = self::SMOOTHING_METRES): array
    {
        $count = count($profile);
        $half = $windowMetres / 2;
        $smoothed = [];
        $from = 0;
        $to = 0;
        $sum = 0.0;

        foreach ($profile as $point) {
            while ($to < $count && $profile[$to]->distanceMetres <= $point->distanceMetres + $half) {
                $sum += $profile[$to]->elevationMetres;
                $to++;
            }

            while ($from < $to && $profile[$from]->distanceMetres < $point->distanceMetres - $half) {
                $sum -= $profile[$from]->elevationMetres;
                $from++;
            }

            $smoothed[] = new ProfilePoint($point->distanceMetres, $sum / max(1, $to - $from));
        }

        return $smoothed;
    }

    /**
     * @param  list<ProfilePoint>  $profile
     */
    public static function ascent(array $profile): int
    {
        return self::totals($profile)['ascent'];
    }

    /**
     * @param  list<ProfilePoint>  $profile
     */
    public static function descent(array $profile): int
    {
        return self::totals($profile)['descent'];
    }

    /**
     * @param  list<ProfilePoint>  $profile
     * @return array{ascent: int, descent: int}
     */
    private static function totals(array $profile): array
    {
        $first = $profile[0] ?? null;

        if ($first === null) {
            return ['ascent' => 0, 'descent' => 0];
        }

        $reference = $first->elevationMetres;
        $ascent = 0.0;
        $descent = 0.0;

        foreach ($profile as $point) {
            $change = $point->elevationMetres - $reference;

            if ($change >= self::THRESHOLD_METRES) {
                $ascent += $change;
                $reference = $point->elevationMetres;
            } elseif (-$change >= self::THRESHOLD_METRES) {
                $descent -= $change;
                $reference = $point->elevationMetres;
            }
        }

        return ['ascent' => (int) round($ascent), 'descent' => (int) round($descent)];
    }
}
