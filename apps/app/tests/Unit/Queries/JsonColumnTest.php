<?php

declare(strict_types=1);

use App\Queries\JsonColumn;

covers(JsonColumn::class);

uses()->group('mutation');

it('reads a line stored as json text or a list', function (): void {
    expect(JsonColumn::line('[[4.9,51.1],[4.91,"51.12"]]'))->toBe([[4.9, 51.1], [4.91, 51.12]])
        ->and(JsonColumn::line([[4.9, 51.1]]))->toBe([[4.9, 51.1]]);
});

it('returns null when an optional line was not stored', function (): void {
    expect(JsonColumn::optionalLine(value: null))->toBeNull()
        ->and(JsonColumn::optionalLine('[]'))->toBe([]);
});

it('rejects a line that is not a list of coordinates', function (mixed $value): void {
    expect(fn (): array => JsonColumn::line($value))->toThrow(RuntimeException::class);
})->with([
    'text' => ['not-json'],
    'object' => ['{"lon":4.9}'],
    'map' => [['lon' => 4.9]],
    'short point' => ['[[4.9]]'],
    'words' => ['[["east","north"]]'],
    'scalar' => [4.9],
]);
