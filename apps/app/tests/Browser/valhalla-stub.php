<?php

declare(strict_types=1);

/*
| A stand-in for the public Valhalla server, for the browser tests on a pull
| request. It answers the two calls the planner makes with straight lines, so
| a pull request does not wait on a fair-use router. A push to main keeps the
| real one.
|
|   php -S 127.0.0.1:8002 tests/Browser/valhalla-stub.php
*/

use App\Integrations\Valhalla\Polyline;

require __DIR__.'/../../vendor/autoload.php';

/**
 * @param  array{0: float, 1: float}  $from
 * @param  array{0: float, 1: float}  $to
 */
function metresBetween(array $from, array $to): float
{
    $lat1 = deg2rad($from[1]);
    $lat2 = deg2rad($to[1]);
    $dLat = $lat2 - $lat1;
    $dLon = deg2rad($to[0] - $from[0]);
    $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

    return 2 * 6_371_000 * asin(min(1.0, sqrt($a)));
}

/**
 * Straight segments through the points, each split into steps so the player
 * and the map framing have coordinates along the way.
 *
 * @param  list<array{0: float, 1: float}>  $points
 * @return list<array{0: float, 1: float}>
 */
function straightLine(array $points, int $steps = 20): array
{
    $line = [$points[0]];

    for ($index = 1, $count = count($points); $index < $count; $index++) {
        $from = $points[$index - 1];
        $to = $points[$index];

        for ($step = 1; $step <= $steps; $step++) {
            $line[] = [
                $from[0] + ($to[0] - $from[0]) * $step / $steps,
                $from[1] + ($to[1] - $from[1]) * $step / $steps,
            ];
        }
    }

    return $line;
}

function reply(int $status, mixed $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_THROW_ON_ERROR);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request = json_decode((string) file_get_contents('php://input'), associative: true);
$locations = is_array($request) && is_array($request['locations'] ?? null) ? $request['locations'] : [];

/** @var list<array{0: float, 1: float}> $points */
$points = [];

foreach ($locations as $location) {
    if (is_array($location) && is_numeric($location['lat'] ?? null) && is_numeric($location['lon'] ?? null)) {
        $points[] = [(float) $location['lon'], (float) $location['lat']];
    }
}

if ($path === '/health') {
    reply(200, ['ok' => true]);

    return;
}

if ($path === '/locate' && $points !== []) {
    reply(200, [['edges' => [['correlated_lat' => $points[0][1], 'correlated_lon' => $points[0][0]]]]]);

    return;
}

if ($path === '/route' && count($points) >= 2) {
    $line = straightLine($points);
    $metres = 0.0;

    for ($index = 1, $count = count($line); $index < $count; $index++) {
        $metres += metresBetween($line[$index - 1], $line[$index]);
    }

    reply(200, [
        'trip' => [
            'summary' => ['length' => $metres / 1000],
            'legs' => [['shape' => Polyline::encode($line)]],
        ],
    ]);

    return;
}

reply(in_array($path, ['/route', '/locate'], strict: true) ? 400 : 404, [
    'error' => 'The stub answers POST /route and POST /locate with at least one location.',
]);
