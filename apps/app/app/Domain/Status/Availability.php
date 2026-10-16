<?php

declare(strict_types=1);

namespace App\Domain\Status;

use DateInterval;
use DateTimeImmutable;

/**
 * Turns counted checks into the days a rider sees: the worst state of each day, and the
 * share of checks that were not down.
 */
final class Availability
{
    /**
     * @param  array<string, array<string, int>>  $counts  checks per day (Y-m-d) and state
     * @return list<array{date: string, state: ServiceState|null}> the oldest day first, today last
     */
    public static function days(array $counts, DateTimeImmutable $today, int $days): array
    {
        $result = [];

        for ($ago = $days - 1; $ago >= 0; $ago--) {
            $date = $today->sub(new DateInterval('P'.$ago.'D'))->format('Y-m-d');
            $result[] = ['date' => $date, 'state' => self::worst(array_keys($counts[$date] ?? []))];
        }

        return $result;
    }

    /**
     * @param  list<string>  $states
     */
    public static function worst(array $states): ?ServiceState
    {
        $worst = null;

        foreach ($states as $state) {
            $serviceState = ServiceState::tryFrom($state);

            if ($serviceState instanceof ServiceState && (! $worst instanceof ServiceState || $serviceState->worseThan($worst))) {
                $worst = $serviceState;
            }
        }

        return $worst;
    }

    /**
     * The share of checks that were not down, rounded down to two places, so a single
     * outage never shows as 100%. Null when there were no checks.
     *
     * @param  array<string, array<string, int>>  $counts
     */
    public static function uptime(array $counts): ?float
    {
        $total = 0;
        $down = 0;

        foreach ($counts as $states) {
            foreach ($states as $state => $count) {
                $total += $count;
                $down += $state === ServiceState::Down->value ? $count : 0;
            }
        }

        if ($total === 0) {
            return null;
        }

        return floor(($total - $down) / $total * 10_000) / 100;
    }
}
