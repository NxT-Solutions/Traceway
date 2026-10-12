<?php

declare(strict_types=1);

namespace App\Queries\Routes;

use App\Domain\Courses\DeviceName;
use App\Domain\Routing\Sport;
use App\Queries\RowReader;
use App\Routes\SavedLeg;
use Illuminate\Support\Facades\DB;

/**
 * The short list a device app shows. The title is for the menu. The name is the one the course carries on the device, so the app can find it after the download.
 */
final class DeviceRoutesQuery
{
    public const int LIMIT = 20;

    /**
     * @return list<array{id: string, title: string, name: string, sport: string, distance_metres: int, ascent_metres: int, bounds: array{0: float, 1: float, 2: float, 3: float}, legs: list<array{n: int, name: string, sport: string, distance_metres: int, ascent_metres: int, bounds: array{0: float, 1: float, 2: float, 3: float}}>}>
     */
    public function forRider(int $riderId): array
    {
        $rows = DB::table('saved_routes')
            ->where('user_id', $riderId)
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'sport', 'distance_metres', 'ascent_metres', 'coordinates', 'legs']);

        $routes = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $name = $reader->string('name');

            $routes[] = [
                'id' => $reader->string('id'),
                'title' => mb_substr(trim($name) === '' ? 'Traceway' : trim($name), 0, 40),
                'name' => DeviceName::forCourse($name)->value,
                'sport' => $reader->string('sport'),
                'distance_metres' => $reader->int('distance_metres'),
                'ascent_metres' => $reader->int('ascent_metres'),
                'bounds' => $this->bounds($reader->line('coordinates')),
                'legs' => $this->legs($name, $reader->legs('legs')),
            ];
        }

        return $routes;
    }

    /**
     * @param  list<SavedLeg>  $savedLegs
     * @return list<array{n: int, name: string, sport: string, distance_metres: int, ascent_metres: int, bounds: array{0: float, 1: float, 2: float, 3: float}}>
     */
    private function legs(string $name, array $savedLegs): array
    {
        $base = trim($name) === '' ? 'Traceway' : trim($name);
        $legs = [];

        foreach ($savedLegs as $index => $savedLeg) {
            if ($savedLeg->sport instanceof Sport) {
                $legs[] = [
                    'n' => $index + 1,
                    'name' => DeviceName::forLeg($base, $index + 1, $savedLeg->sport)->value,
                    'sport' => $savedLeg->sport->value,
                    'distance_metres' => $savedLeg->distanceMetres,
                    'ascent_metres' => $savedLeg->ascentMetres,
                    'bounds' => $this->bounds($savedLeg->coordinates),
                ];
            }
        }

        return $legs;
    }

    /**
     * South, west, north, east, so a device can frame its map before the line arrives.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function bounds(array $line): array
    {
        $first = $line[0] ?? [0.0, 0.0];
        $south = $first[1];
        $north = $first[1];
        $west = $first[0];
        $east = $first[0];

        foreach ($line as [$longitude, $latitude]) {
            $south = min($south, $latitude);
            $north = max($north, $latitude);
            $west = min($west, $longitude);
            $east = max($east, $longitude);
        }

        return [round($south, 5), round($west, 5), round($north, 5), round($east, 5)];
    }
}
