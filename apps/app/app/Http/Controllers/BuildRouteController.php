<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Routing\GeneratedRoute;
use App\Domain\Routing\RouteCleanliness;
use App\Domain\Routing\RoutingEngine;
use App\Http\Requests\BuildRouteRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class BuildRouteController extends Controller
{
    public function __invoke(BuildRouteRequest $request, RoutingEngine $engine): JsonResponse
    {
        $waypoints = $request->waypoints();

        if (count($waypoints) < 2) {
            return response()->json([
                'message' => 'Add a point on the map, then draw the route.',
            ], 422);
        }

        $finish = $request->boolean('finish');
        $start = $waypoints[0];
        $last = $waypoints[array_key_last($waypoints)];

        if (! $request->boolean('follow')) {
            $closing = $finish && $last !== $start ? [$last, $start] : [];
            $line = $closing === [] ? $waypoints : array_merge($waypoints, [$start]);

            return response()->json($this->payload($line, $waypoints, $finish ? $closing : null, $waypoints));
        }

        $ridden = $waypoints;
        $closing = null;

        try {
            $through = $engine->trace($request->toQuery(), $waypoints);
            $trimmed = RouteCleanliness::withoutSpurs($through->coordinates);
            $onThePoint = 25.0;
            $userLine = RouteCleanliness::keepingVisits($trimmed, $through->coordinates, $waypoints, $onThePoint);
            $ridden = count($userLine) >= 2 ? $userLine : $through->coordinates;

            if (! RouteCleanliness::visits($ridden, $waypoints, $onThePoint)) {
                return response()->json([
                    'message' => 'The roads between those points do not reach each one. Move a point onto a road the ride can use.',
                ], 422);
            }

            if ($finish && $last !== $start) {
                $closing = $engine->closeLoop($request->toQuery(), $ridden, $last, $start);
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Those points did not join along the roads. Try a point closer to a road.',
            ], 502);
        }

        $user = $ridden;
        $tail = $closing instanceof GeneratedRoute ? $closing->coordinates : [];
        $line = $tail === [] ? $user : array_merge($user, array_slice($tail, 1));

        return response()->json($this->payload($line, $user, $finish ? $tail : null, $waypoints, $through->ascentMetres));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $user
     * @param  list<array{0: float, 1: float}>|null  $finish
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return array<string, mixed>
     */
    private function payload(array $line, array $user, ?array $finish, array $waypoints, int $ascent = 0): array
    {
        $userMetres = self::lengthMetres($user);
        $finishMetres = $finish === null ? 0 : self::lengthMetres($finish);

        return [
            'distance_metres' => $userMetres + $finishMetres,
            'ascent_metres' => $ascent,
            'coordinates' => $line,
            'user_coordinates' => $user,
            'finish_coordinates' => $finish ?? [],
            'user_metres' => $userMetres,
            'finish_metres' => $finishMetres,
            'legs_metres' => self::legs($user, $waypoints),
        ];
    }

    /**
     * Distance along the drawn line from each point to the next.
     *
     * @param  list<array{0: float, 1: float}>  $line
     * @param  list<array{0: float, 1: float}>  $waypoints
     * @return list<int>
     */
    private static function legs(array $line, array $waypoints): array
    {
        $indexes = [];

        foreach ($waypoints as $point) {
            $best = 0;
            $bestDistance = INF;

            foreach ($line as $index => $vertex) {
                $distance = self::metres($point, $vertex);

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
            $legs[] = self::lengthMetres(array_slice($line, $from, $to - $from + 1));
        }

        return $legs;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coordinates
     */
    private static function lengthMetres(array $coordinates): int
    {
        $metres = 0.0;

        for ($index = 1, $count = count($coordinates); $index < $count; $index++) {
            $from = $coordinates[$index - 1] ?? null;
            $to = $coordinates[$index] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $metres += self::metres($from, $to);
        }

        return (int) round($metres);
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private static function metres(array $from, array $to): float
    {
        $lat1 = deg2rad($from[1]);
        $lat2 = deg2rad($to[1]);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($to[0] - $from[0]);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
    }
}
