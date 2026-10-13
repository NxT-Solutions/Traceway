<?php

declare(strict_types=1);

use App\Domain\Courses\GpxCourse;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Distance;

covers(GpxCourse::class);

uses()->group('mutation');

it('writes a track a GPS can follow', function (): void {
    $gpx = GpxCourse::encode('  <Dawn & "back">  ', [[4.9, 51.1], [4.91, 51.12]]);

    expect($gpx)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($gpx)->toContain('<name>&lt;Dawn &amp; &quot;back&quot;&gt;</name>')
        ->and(substr_count($gpx, '<trkpt '))->toBe(2)
        ->and($gpx)->toContain('<trkpt lat="51.1" lon="4.9"></trkpt>')
        ->and($gpx)->toContain('<trkpt lat="51.12" lon="4.91"></trkpt>')
        ->and(GpxCourse::encode('   ', [[0.0, 0.0], [1.0, 1.0]]))->toContain('<name>Traceway</name>');
});

it('refuses a course with fewer than two points', function (int $count): void {
    $coordinates = array_slice([[4.9, 51.1], [4.91, 51.12]], 0, $count);

    expect(fn (): string => GpxCourse::encode('Dawn', $coordinates))->toThrow(InvalidArgumentException::class);
})->with([
    'none' => [0],
    'one' => [1],
]);

it('writes one track per leg and a waypoint for a swim pin', function (): void {
    $gpx = GpxCourse::encodeLegs('Tri & co', [
        ['name' => 'Swim', 'type' => 'swim', 'coordinates' => [[4.88, 51.1]]],
        ['name' => 'Tri 2 Road', 'type' => 'road', 'coordinates' => [[4.9, 51.1], [4.91, 51.12]]],
        ['name' => 'Tri 3 Run', 'type' => 'run', 'coordinates' => [[4.9, 51.1], [4.92, 51.11], [4.9, 51.1]]],
    ]);

    expect($gpx)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($gpx)->toContain('<metadata><name>Tri &amp; co</name></metadata>')
        ->and(substr_count($gpx, '<trk>'))->toBe(2)
        ->and(substr_count($gpx, '<trkpt '))->toBe(5)
        ->and($gpx)->toContain('<wpt lat="51.1" lon="4.88"><name>Swim</name><type>swim</type></wpt>')
        ->and($gpx)->toContain('<trk><name>Tri 2 Road</name><type>road</type><trkseg><trkpt lat="51.1" lon="4.9"></trkpt>')
        ->and($gpx)->toContain('<name>Tri 3 Run</name><type>run</type>')
        ->and($gpx)->toEndWith('</trkseg></trk></gpx>')
        ->and(GpxCourse::encodeLegs(' ', [['name' => 'Run', 'type' => 'run', 'coordinates' => [[0.0, 0.0], [1.0, 1.0]]]]))
        ->toContain('<metadata><name>Traceway</name></metadata>');
});

it('refuses a multisport course with no legs', function (): void {
    expect(fn (): string => GpxCourse::encodeLegs('Tri', []))->toThrow(InvalidArgumentException::class);
});

it('gives each point its height and each stop a waypoint', function (): void {
    $line = [[4.9, 51.1], [4.92, 51.1]];
    $metres = (int) round(Distance::along($line));
    $gpx = GpxCourse::encode('Hill', $line, new RouteDetails(
        profile: [new ProfilePoint(0, 12.0), new ProfilePoint($metres, 31.56)],
        ascentMetres: 20,
        descentMetres: 0,
        surfaces: [],
        cues: [],
        stops: [
            new Stop(400, 4.905, 51.1, StopKind::Water),
            new Stop(900, 4.91, 51.1, StopKind::Cafe, 'Tea & Cake'),
        ],
    ));

    expect($gpx)->toContain('<trkpt lat="51.1" lon="4.9"><ele>12</ele></trkpt>')
        ->and($gpx)->toContain('<trkpt lat="51.1" lon="4.92"><ele>31.6</ele></trkpt>')
        ->and($gpx)->toContain('<wpt lat="51.1" lon="4.905"><name>Water</name><sym>Drinking Water</sym><type>water</type></wpt>')
        ->and($gpx)->toContain('<name>Tea &amp; Cake</name><sym>Restaurant</sym>')
        ->and(strpos($gpx, '<wpt'))->toBeLessThan(strpos($gpx, '<trk>'));
});

it('gives each leg of a plan its own heights and stops', function (): void {
    $gpx = GpxCourse::encodeLegs('Brick', [
        ['name' => 'Brick 1 Ride', 'type' => 'road', 'coordinates' => [[4.9, 51.1], [4.92, 51.1]], 'details' => new RouteDetails(
            profile: [new ProfilePoint(0, 5.0)],
            ascentMetres: 0,
            descentMetres: 0,
            surfaces: [],
            cues: [],
            stops: [new Stop(100, 4.91, 51.1, StopKind::Toilet)],
        )],
        ['name' => 'Brick 2 Run', 'type' => 'run', 'coordinates' => [[4.9, 51.1], [4.91, 51.1]]],
    ]);

    expect($gpx)->toContain('<sym>Restroom</sym>')
        ->and(substr_count($gpx, '<ele>5</ele>'))->toBe(2)
        ->and($gpx)->toContain('<trkpt lat="51.1" lon="4.91"></trkpt>');
});
