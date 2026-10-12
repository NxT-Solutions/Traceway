<?php

declare(strict_types=1);

use App\Domain\Courses\FitCourse;
use App\Domain\Routing\Sport;

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
    $start = strpos($bytes, 'Z');

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
