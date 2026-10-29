<?php

declare(strict_types=1);

use App\Domain\Courses\FitCourse;
use App\Domain\Routing\Details\Cue;
use App\Domain\Routing\Details\ProfilePoint;
use App\Domain\Routing\Details\RouteDetails;
use App\Domain\Routing\Details\Stop;
use App\Domain\Routing\Details\StopKind;
use App\Domain\Routing\Details\Turn;
use App\Domain\Routing\Distance;
use App\Domain\Routing\Sport;
use Tests\Support\FitMessages;

it('writes a course file a head unit can open', function (): void {
    $bytes = FitCourse::encode('Dawn ride', Sport::Road, [
        [3.72, 51.05],
        [3.74, 51.06],
        [3.73, 51.07],
    ]);

    expect(substr($bytes, 8, 4))->toBe('.FIT')
        ->and(ord($bytes[0]))->toBe(14)
        ->and($bytes)->toContain('Dawn ride')
        ->and(strlen($bytes))->toBeGreaterThan(40);

    $headerCrc = unpack('v', substr($bytes, 12, 2));
    $fileCrc = unpack('v', substr($bytes, -2));

    expect($headerCrc)->toBeArray()
        ->and($fileCrc)->toBeArray()
        ->and($headerCrc[1])->toBe(fitCrc(substr($bytes, 0, 12)))
        ->and($fileCrc[1])->toBe(fitCrc(substr($bytes, 0, -2)));
});

it('keeps a long accented name whole in the sixteen-byte name field', function (): void {
    $bytes = FitCourse::encode('Zürich Ir 2 Run', Sport::Run, [[3.72, 51.05], [3.74, 51.06]]);
    // The file holds the time it was made, and any of its bytes can be a 'Z',
    // so the field is found by the name itself, not by its first letter.
    $start = strpos($bytes, 'Zürich');

    expect($start)->toBeInt();

    $field = substr($bytes, (int) $start, 16);

    expect(rtrim($field, "\0"))->toBe('Zürich Ir 2 Ru')
        ->and(mb_check_encoding(rtrim($field, "\0"), 'UTF-8'))->toBeTrue()
        ->and($field[15])->toBe("\0");
});

it('refuses a course with a single point', function (): void {
    expect(fn (): string => FitCourse::encode('Dawn ride', Sport::Road, [[3.72, 51.05]]))
        ->toThrow(InvalidArgumentException::class);
});

it('gives each point its height, so the watch can show the climbs', function (): void {
    $line = [[3.72, 51.05], [3.74, 51.05], [3.76, 51.05]];
    $metres = (int) round(Distance::along($line));
    $bytes = FitCourse::encode('Hill ride', Sport::Road, $line, new RouteDetails(
        profile: [new ProfilePoint(0, 10.0), new ProfilePoint($metres, 110.0)],
        ascentMetres: 100,
        descentMetres: 0,
        surfaces: [],
        cues: [],
        stops: [],
    ));

    $records = FitMessages::of($bytes, 20);
    $lap = FitMessages::of($bytes, 19)[0] ?? [];
    $altitudes = array_map(fn (array $record): float => ((int) $record[2]) / 5 - 500, $records);

    expect($altitudes[0])->toEqualWithDelta(10.0, 0.2)
        ->and($altitudes[1])->toEqualWithDelta(60.0, 0.5)
        ->and($altitudes[2])->toEqualWithDelta(110.0, 0.2)
        ->and($lap[21] ?? null)->toBe(100)
        ->and($lap[22] ?? null)->toBe(0)
        ->and(FitMessages::of($bytes, 32))->toBe([]);
});

it('writes turns and stops as course points, in order along the course', function (): void {
    $line = [[3.72, 51.05], [3.74, 51.05], [3.76, 51.05]];
    $bytes = FitCourse::encode('Cafe ride', Sport::Gravel, $line, new RouteDetails(
        profile: [],
        ascentMetres: 0,
        descentMetres: 0,
        surfaces: [],
        cues: [
            new Cue(2000, 3.75, 51.05, Turn::SharpRight),
            new Cue(500, 3.727, 51.05, Turn::Left, 'Kerkstraat'),
        ],
        stops: [
            new Stop(1000, 3.734, 51.05, StopKind::Water),
            new Stop(1500, 3.741, 51.05, StopKind::Cafe, 'Het Wiel en de Koffie'),
            new Stop(1700, 3.744, 51.05, StopKind::Toilet),
        ],
    ));

    $points = FitMessages::of($bytes, 32);

    expect(array_map(fn (array $point): mixed => $point[5], $points))->toBe([6, 3, 4, 39, 22])
        ->and(array_map(fn (array $point): mixed => $point[6], $points))->toBe(['Kerkstraat', 'Water', 'Het Wiel en de ', 'Toilet', 'Sharp right'])
        ->and(array_map(fn (array $point): mixed => $point[254], $points))->toBe([0, 1, 2, 3, 4])
        ->and($points[0][4])->toBeGreaterThan(49_000)->toBeLessThan(50_100)
        ->and(FitMessages::of($bytes, 20)[0])->not->toHaveKey(2)
        ->and(unpack('v', substr($bytes, -2))[1] ?? null)->toBe(fitCrc(substr($bytes, 0, -2)));
});

it('keeps every stop and drops slight bends first when a watch cannot hold them all', function (): void {
    $cues = [];

    for ($index = 0; $index < 250; $index++) {
        $cues[] = new Cue(10 + $index * 10, 3.72, 51.05, $index % 2 === 0 ? Turn::SlightLeft : Turn::Right);
    }

    $bytes = FitCourse::encode('Busy town', Sport::Run, [[3.72, 51.05], [3.76, 51.05]], new RouteDetails(
        profile: [],
        ascentMetres: 0,
        descentMetres: 0,
        surfaces: [],
        cues: $cues,
        stops: [new Stop(2600, 3.75, 51.05, StopKind::Water)],
    ));

    $types = array_map(fn (array $point): mixed => $point[5], FitMessages::of($bytes, 32));

    expect($types)->toHaveCount(FitCourse::MAX_COURSE_POINTS)
        ->and(array_count_values($types))->toEqual([3 => 1, 7 => 125, 19 => 74]);
});

function fitCrc(string $bytes): int
{
    $table = [
        0x0000, 0xCC01, 0xD801, 0x1400, 0xF001, 0x3C00, 0x2800, 0xE401,
        0xA001, 0x6C00, 0x7800, 0xB401, 0x5000, 0x9C01, 0x8801, 0x4400,
    ];
    $crc = 0;
    $length = strlen($bytes);

    for ($index = 0; $index < $length; $index++) {
        $byte = ord($bytes[$index]);
        $tmp = $table[$crc & 0xF];
        $crc = ($crc >> 4) & 0x0FFF;
        $crc = $crc ^ $tmp ^ $table[$byte & 0xF];
        $tmp = $table[$crc & 0xF];
        $crc = ($crc >> 4) & 0x0FFF;
        $crc = $crc ^ $tmp ^ $table[($byte >> 4) & 0xF];
    }

    return $crc & 0xFFFF;
}
