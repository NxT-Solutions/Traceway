<?php

declare(strict_types=1);

namespace App\Http\Responses;

use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: 'BuiltRoute',
    required: ['distance_metres', 'ascent_metres', 'coordinates', 'user_coordinates', 'finish_coordinates', 'user_metres', 'finish_metres', 'legs_metres'],
    properties: [
        new Property(property: 'distance_metres', type: 'integer'),
        new Property(property: 'ascent_metres', type: 'integer'),
        new Property(property: 'coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'user_coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'finish_coordinates', ref: '#/components/schemas/CoordinateLine'),
        new Property(property: 'user_metres', type: 'integer'),
        new Property(property: 'finish_metres', type: 'integer'),
        new Property(property: 'legs_metres', type: 'array', items: new Items(type: 'integer')),
    ],
)]
final readonly class BuiltRoute
{
    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $user
     * @param  list<array{0: float, 1: float}>|null  $finish
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public function __construct(
        public array $line,
        public array $user,
        public ?array $finish,
        public array $waypoints,
        public int $ascentMetres,
    ) {}

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $user
     * @param  list<array{0: float, 1: float}>|null  $finish
     * @param  list<array{0: float, 1: float}>  $waypoints
     */
    public static function from(array $line, array $user, ?array $finish, array $waypoints, int $ascentMetres = 0): self
    {
        return new self($line, $user, $finish, $waypoints, $ascentMetres);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $userMetres = $this->lengthMetres($this->user);
        $finishMetres = $this->finish === null ? 0 : $this->lengthMetres($this->finish);

        return [
            'distance_metres' => $userMetres + $finishMetres,
            'ascent_metres' => $this->ascentMetres,
            'coordinates' => $this->line,
            'user_coordinates' => $this->user,
            'finish_coordinates' => $this->finish ?? [],
            'user_metres' => $userMetres,
            'finish_metres' => $finishMetres,
            'legs_metres' => $this->legs($this->user, $this->waypoints),
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<int>
     */
    private function legs(array $line, array $waypoints): array
    {
        $indexes = [];

        foreach ($waypoints as $waypoint) {
            $best = 0;
            $bestDistance = INF;

            foreach ($line as $index => $vertex) {
                $distance = $this->metres($waypoint, $vertex);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $index;
                }
            }

            $indexes[] = $best;
        }

        $legs = [];

        for ($index = 1, $count = count($indexes); $index < $count; $index++) {
            $from = min($indexes[$index - 1], $indexes[$index]);
            $to = max($indexes[$index - 1], $indexes[$index]);
            $legs[] = $this->lengthMetres(array_slice($line, $from, $to - $from + 1));
        }

        return $legs;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private function lengthMetres(array $coordinates): int
    {
        $metres = 0.0;

        for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
            $from = $coordinates[$index - 1] ?? null;
            $to = $coordinates[$index] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $metres += $this->metres($from, $to);
        }

        return (int) round($metres);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private function metres(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($to[0] - $from[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
    }
}
