<?php

declare(strict_types=1);

use App\Domain\Courses\GpxCourse;

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
