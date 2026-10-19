<?php

declare(strict_types=1);

/*
| A stand-in for the public Valhalla server, for every browser test run. It
| answers the planner's routing calls with straight lines, and the
| calls that describe a line with a hill, two turns, and a gravel end, so the
| climb chart, the surfaces, and the turns show without a fair-use router. It
| also stands in for the map tiles with no stops, and for Nominatim with one
| village name for every pin and the same two places for every search, so no
| test waits on those servers. It also draws the browser's basemap, which
| mock-servers.mjs hands the page in place of OpenFreeMap's.
|
|   php -S 127.0.0.1:8002 tests/Browser/valhalla-stub.php
*/

use App\Integrations\Valhalla\Polyline;
use Tests\Support\VectorTiles;

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
 * Segments through the points, each split into steps so the player and the
 * map framing have coordinates along the way. Each bows 20 metres to one
 * side at its middle, as a road bends: the planner thins a line to the
 * metre, which would leave a straight one with its corners alone.
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
        $squeeze = cos(deg2rad(($from[1] + $to[1]) / 2));
        $across = ($to[0] - $from[0]) * $squeeze;
        $up = $to[1] - $from[1];
        $length = hypot($across, $up);
        $bow = $length > 0.0 ? 20 / 111_320 / $length : 0.0;

        for ($step = 1; $step <= $steps; $step++) {
            $share = $step / $steps;
            $side = sin(M_PI * $share) * $bow;
            $line[] = [
                $from[0] + ($to[0] - $from[0]) * $share - $up * $side / $squeeze,
                $from[1] + ($to[1] - $from[1]) * $share + $across * $side,
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

// The line a describing call is about, as the planner sent it.
$shape = is_array($request) && is_string($request['encoded_polyline'] ?? null) ? $request['encoded_polyline'] : null;
$line = $shape === null ? [] : Polyline::decode($shape);

if ($path === '/trace_route' && count($line) >= 2) {
    $last = count($line) - 1;

    reply(200, ['trip' => ['legs' => [[
        'shape' => $shape,
        'maneuvers' => [
            ['type' => 1, 'begin_shape_index' => 0, 'street_names' => ['Kerkstraat']],
            ['type' => 10, 'begin_shape_index' => intdiv($last, 3), 'street_names' => ['Molenweg']],
            ['type' => 15, 'begin_shape_index' => intdiv($last * 2, 3), 'street_names' => ['Bosdreef']],
            ['type' => 4, 'begin_shape_index' => $last],
        ],
    ]]]]);

    return;
}

if ($path === '/trace_attributes' && count($line) >= 2) {
    $metres = 0.0;

    for ($index = 1, $count = count($line); $index < $count; $index++) {
        $metres += metresBetween($line[$index - 1], $line[$index]);
    }

    reply(200, ['edges' => [
        ['surface' => 'paved_smooth', 'length' => $metres * 0.8 / 1000],
        ['surface' => 'gravel', 'length' => $metres * 0.2 / 1000],
    ]]);

    return;
}

if ($path === '/height' && count($line) >= 2) {
    $total = 0.0;

    for ($index = 1, $count = count($line); $index < $count; $index++) {
        $total += metresBetween($line[$index - 1], $line[$index]);
    }

    $step = is_numeric($request['resample_distance'] ?? null) ? max(10.0, (float) $request['resample_distance']) : 30.0;
    $heights = [];

    for ($at = 0.0; $at <= $total; $at += $step) {
        $heights[] = [round($at), round(20 + 25 * sin(M_PI * $at / max($total, 1.0)), 1)];
    }

    reply(200, ['range_height' => $heights]);

    return;
}

if ($path === '/planet') {
    reply(200, ['tilejson' => '3.0.0', 'tiles' => ['http://127.0.0.1:8002/planet/stub/{z}/{x}/{y}.pbf']]);

    return;
}

// An empty tile: no water, toilets, or cafés anywhere.
if (str_starts_with($path, '/planet/stub/')) {
    http_response_code(200);
    header('Content-Type: application/vnd.mapbox-vector-tile');

    return;
}

// The browser's basemap, which mock-servers.mjs hands the page in place of
// OpenFreeMap's: a grid of streets for a tap to snap to, and a few towns
// around the planner's opening view, so the search bar finds names on the map
// without a server.
if (preg_match('#^/styles/(\w+)$#', $path, $style) === 1) {
    reply(200, [
        'version' => 8,
        'name' => 'mock '.$style[1],
        'sources' => ['openmaptiles' => [
            'type' => 'vector',
            'tiles' => ['https://tiles.openfreemap.org/mock/{z}/{x}/{y}.pbf'],
            'maxzoom' => 14,
        ]],
        'layers' => [
            ['id' => 'background', 'type' => 'background', 'paint' => ['background-color' => $style[1] === 'dark' ? '#1c2230' : '#eef0e6']],
            ['id' => 'road_minor', 'type' => 'line', 'source' => 'openmaptiles', 'source-layer' => 'transportation', 'paint' => ['line-color' => $style[1] === 'dark' ? '#3a4254' : '#ffffff', 'line-width' => 2]],
            ['id' => 'place', 'type' => 'circle', 'source' => 'openmaptiles', 'source-layer' => 'place', 'paint' => ['circle-radius' => 3, 'circle-color' => '#8a8f80']],
        ],
    ]);

    return;
}

if (preg_match('#^/basemap/(\d+)/(\d+)/(\d+)\.pbf$#', $path, $tile) === 1) {
    [, $zoom, $column, $row] = array_map(intval(...), $tile);
    $towns = ['Gent' => [3.7174, 51.0543], 'Gentbrugge' => [3.7621, 51.0378], 'Westerlo' => [4.9166, 51.0907], 'Oevel' => [4.9411, 51.1012]];
    $places = [];

    foreach ($towns as $name => [$longitude, $latitude]) {
        $tiles = 2 ** $zoom;
        $across = ($longitude + 180) / 360 * $tiles - $column;
        $down = (1 - log(tan(deg2rad($latitude)) + 1 / cos(deg2rad($latitude))) / M_PI) / 2 * $tiles - $row;

        if ($across >= 0 && $across < 1 && $down >= 0 && $down < 1) {
            $places[] = ['at' => [$across, $down], 'tags' => ['name' => $name, 'class' => 'town']];
        }
    }

    // A grid of streets in every tile, so a tap anywhere has a road to snap to.
    $roads = [];

    for ($line = 1; $line <= 8; $line++) {
        $roads[] = ['line' => [[0.0, $line / 9], [1.0, $line / 9]], 'tags' => ['class' => 'minor']];
        $roads[] = ['line' => [[$line / 9, 0.0], [$line / 9, 1.0]], 'tags' => ['class' => 'minor']];
    }

    http_response_code(200);
    header('Content-Type: application/vnd.mapbox-vector-tile');
    echo VectorTiles::tile(['transportation' => $roads, 'place' => $places]);

    return;
}

// Two places for any search, the town first, so a test can pick one.
if ($path === '/search') {
    reply(200, [
        [
            'lat' => '51.0907', 'lon' => '4.9166', 'name' => 'Westerlo',
            'display_name' => 'Westerlo, Turnhout, Antwerp, Flanders, Belgium',
            'address' => ['town' => 'Westerlo', 'county' => 'Turnhout', 'country' => 'Belgium'],
        ],
        [
            'lat' => '51.1012', 'lon' => '4.9411', 'name' => 'Oevel',
            'display_name' => 'Oevel, Westerlo, Turnhout, Antwerp, Belgium',
            'address' => ['village' => 'Oevel', 'town' => 'Westerlo', 'country' => 'Belgium'],
        ],
    ]);

    return;
}

if ($path === '/reverse') {
    reply(200, ['name' => 'Stubbington', 'address' => ['village' => 'Stubbington']]);

    return;
}

reply(in_array($path, ['/route', '/locate'], strict: true) ? 400 : 404, [
    'error' => 'The stub answers /route, /locate, /trace_route, /trace_attributes, /height, /planet, its tiles, /styles, /basemap, /search, and /reverse.',
]);
