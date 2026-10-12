<?php

declare(strict_types=1);

use App\Domain\Courses\DeviceName;
use App\Domain\Routing\Sport;

covers(DeviceName::class);

uses()->group('mutation');

it('keeps a name a head unit can show', function (string $input, string $expected): void {
    expect((new DeviceName($input))->value)->toBe($expected);
})->with([
    'plain' => ['Morning loop', 'Morning loop'],
    'trimmed' => ['  Dawn ride  ', 'Dawn ride'],
    'fifteen characters' => ['123456789012345', '123456789012345'],
]);

it('rejects an empty or oversized device name', function (string $input): void {
    expect(fn (): DeviceName => new DeviceName($input))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'sixteen characters' => ['1234567890123456'],
]);

it('names each leg of a multisport plan so the leg and sport always fit', function (string $base, int $position, Sport $sport, string $expected): void {
    $name = DeviceName::forLeg($base, $position, $sport)->value;

    expect($name)->toBe($expected)
        ->and(mb_strlen($name))->toBeLessThanOrEqual(15);
})->with([
    'short' => ['Brick', 2, Sport::Run, 'Brick 2 Run'],
    'long plan name' => ['Sunday triathlon rehearsal', 1, Sport::Road, 'Sunday t 1 Road'],
    'longest sport' => ['Duathlon', 3, Sport::Gravel, 'Duathl 3 Gravel'],
    'trail' => ['Tri', 3, Sport::TrailRun, 'Tri 3 Trail'],
    'mountain bike' => ['Tri', 2, Sport::Mtb, 'Tri 2 MTB'],
    'blank' => ['   ', 1, Sport::Hike, 'Leg 1 Hike'],
    'walk' => ['Tri', 4, Sport::Walk, 'Tri 4 Walk'],
    'gravel' => ['Tri', 2, Sport::Gravel, 'Tri 2 Gravel'],
    'multibyte' => ['Zürich Ironman Probe', 2, Sport::Run, 'Zürich Ir 2 Run'],
    'space at the cut' => ['Big day out', 1, Sport::Road, 'Big day 1 Road'],
]);
