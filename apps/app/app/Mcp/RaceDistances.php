<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Routing\Sport;

/**
 * Race distances for multisport plans. The planner keeps the same table in
 * resources/js/lib/multisport.ts. Change both together.
 */
final class RaceDistances
{
    /**
     * Each race: the swim in kilometres (null without a swim), then the land
     * legs in order as [sport, kilometres].
     *
     * @var array<string, array<string, array{swim: float|null, legs: list<array{0: Sport, 1: float}>}>>
     */
    public const array EVENTS = [
        'triathlon' => [
            'super_sprint' => ['swim' => 0.4, 'legs' => [[Sport::Road, 10.0], [Sport::Run, 2.5]]],
            'eighth' => ['swim' => 0.5, 'legs' => [[Sport::Road, 20.0], [Sport::Run, 5.0]]],
            'sprint' => ['swim' => 0.75, 'legs' => [[Sport::Road, 20.0], [Sport::Run, 5.0]]],
            'quarter' => ['swim' => 1.0, 'legs' => [[Sport::Road, 42.0], [Sport::Run, 10.0]]],
            'olympic' => ['swim' => 1.5, 'legs' => [[Sport::Road, 40.0], [Sport::Run, 10.0]]],
            't100' => ['swim' => 2.0, 'legs' => [[Sport::Road, 80.0], [Sport::Run, 18.0]]],
            'ironman_70_3' => ['swim' => 1.9, 'legs' => [[Sport::Road, 90.0], [Sport::Run, 21.1]]],
            'ironman' => ['swim' => 3.8, 'legs' => [[Sport::Road, 180.0], [Sport::Run, 42.2]]],
        ],
        'duathlon' => [
            'sprint' => ['swim' => null, 'legs' => [[Sport::Run, 5.0], [Sport::Road, 20.0], [Sport::Run, 2.5]]],
            'standard' => ['swim' => null, 'legs' => [[Sport::Run, 10.0], [Sport::Road, 40.0], [Sport::Run, 5.0]]],
            'powerman' => ['swim' => null, 'legs' => [[Sport::Run, 10.0], [Sport::Road, 150.0], [Sport::Run, 30.0]]],
        ],
        'brick' => [
            'sprint' => ['swim' => null, 'legs' => [[Sport::Road, 20.0], [Sport::Run, 5.0]]],
            'olympic' => ['swim' => null, 'legs' => [[Sport::Road, 40.0], [Sport::Run, 10.0]]],
            't100' => ['swim' => null, 'legs' => [[Sport::Road, 80.0], [Sport::Run, 18.0]]],
            'ironman_70_3' => ['swim' => null, 'legs' => [[Sport::Road, 90.0], [Sport::Run, 21.1]]],
            'ironman' => ['swim' => null, 'legs' => [[Sport::Road, 180.0], [Sport::Run, 42.2]]],
        ],
        'aquabike' => [
            'standard' => ['swim' => 1.5, 'legs' => [[Sport::Road, 40.0]]],
            'middle' => ['swim' => 1.9, 'legs' => [[Sport::Road, 90.0]]],
            'long' => ['swim' => 3.8, 'legs' => [[Sport::Road, 180.0]]],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function events(): array
    {
        return array_keys(self::EVENTS);
    }

    /**
     * @return list<string>
     */
    public static function races(): array
    {
        $races = [];

        foreach (self::EVENTS as $distances) {
            foreach (array_keys($distances) as $race) {
                $races[$race] = $race;
            }
        }

        return array_values($races);
    }

    /**
     * @return array{swim: float|null, legs: list<array{0: Sport, 1: float}>}|null
     */
    public static function find(string $event, string $race): ?array
    {
        return self::EVENTS[$event][$race] ?? null;
    }
}
