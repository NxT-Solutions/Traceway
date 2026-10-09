<?php

declare(strict_types=1);

use App\Domain\Courses\DeviceName;

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
