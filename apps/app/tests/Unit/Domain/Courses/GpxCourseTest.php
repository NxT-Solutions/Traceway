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
