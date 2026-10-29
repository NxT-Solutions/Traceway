<?php

declare(strict_types=1);

namespace App\Domain\Status;

use DateTimeImmutable;

/**
 * Turns counted checks into the bars a rider sees: the worst state of each hour, day, or
 * week, and the share of checks that were not down.
 */
final class Availability
{
    /**
     * Folds checks counted per hour or per day into bars of $hours each, from $first on.
     * A bar with no checks has no state.
     *
     * @param  array<string, array<string, int>>  $counts  checks per hour or day, by its start (Y-m-d H:i:s), and state
     * @return list<array{start: DateTimeImmutable, state: ServiceState|null}> the oldest bar first, now last
     */
    public static function bars(array $counts, DateTimeImmutable $first, int $bars, int $hours): array
    {
        $seconds = $hours * 3_600;
        $states = array_fill(0, $bars, []);

        foreach ($counts as $start => $byState) {
            $at = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $start, $first->getTimezone());
            $index = $at instanceof DateTimeImmutable ? intdiv($at->getTimestamp() - $first->getTimestamp(), $seconds) : -1;

            if ($index >= 0 && $index < $bars && $at >= $first) {
                $states[$index] = [...$states[$index], ...array_keys($byState)];
            }
        }

        $result = [];

        foreach ($states as $index => $seen) {
            $result[] = [
                'start' => $first->modify(sprintf('+%d hours', $index * $hours)),
                'state' => self::worst(array_map(strval(...), $seen)),
            ];
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
