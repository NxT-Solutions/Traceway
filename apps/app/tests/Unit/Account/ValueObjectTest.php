<?php

declare(strict_types=1);

use App\Account\Email;
use App\Account\RiderId;
use App\Account\RiderName;
use App\Routes\CoordinateLine;
use App\Routes\RouteId;
use App\Routes\RouteName;

covers(Email::class, RiderId::class, RiderName::class, CoordinateLine::class, RouteId::class, RouteName::class);

uses()->group('mutation');

it('normalizes a rider email and name', function (): void {
    $email = new Email('  Road@Traceway.test ');
    $name = new RiderName('  Road rider  ');

    expect($email->value)->toBe('road@traceway.test')
        ->and($name->value)->toBe('Road rider')
        ->and($email->equals(new Email('ROAD@traceway.test')))->toBeTrue()
        ->and($email->equals(new Email('other@traceway.test')))->toBeFalse()
        ->and($name->equals(new RiderName('Road rider')))->toBeTrue()
        ->and($name->equals(new RiderName('Other')))->toBeFalse();
});

it('keeps a rider id and a name at the edges of the allowed length', function (): void {
    expect((new RiderId(1))->value)->toBe(1)
        ->and((new RiderName('A'))->value)->toBe('A')
        ->and((new RiderName(str_repeat('a', 255)))->value)->toHaveLength(255);
});

it('rejects a rider id, name, or email that cannot identify a rider', function (string $kind): void {
    $build = match ($kind) {
        'zero' => fn (): RiderId => new RiderId(0),
        'negative' => fn (): RiderId => new RiderId(-1),
        'blank name' => fn (): RiderName => new RiderName('   '),
        'long name' => fn (): RiderName => new RiderName(str_repeat('a', 256)),
        'blank email' => fn (): Email => new Email('   '),
        'email' => fn (): Email => new Email('not-an-email'),
    };

    expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'zero',
    'negative',
    'blank name',
    'long name',
    'blank email',
    'email',
]);

it('accepts a route id in either case and a name of eighty characters', function (): void {
    $lower = new RouteId('00000000-0000-0000-0000-000000000000');
    $upper = new RouteId('AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE');
    $name = new RouteName('  '.str_repeat('a', 80).'  ');

    expect($lower->value)->toBe('00000000-0000-0000-0000-000000000000')
        ->and($upper->value)->toBe('AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE')
        ->and($name->value)->toHaveLength(80);
});

it('rejects a route id or name a library cannot store', function (string $kind): void {
    $build = match ($kind) {
        'id' => fn (): RouteId => new RouteId('not-a-uuid'),
        'blank name' => fn (): RouteName => new RouteName('   '),
        'long name' => fn (): RouteName => new RouteName(str_repeat('a', 81)),
    };

    expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'id',
    'blank name',
    'long name',
]);

it('keeps a ride on the map and drops an empty optional line', function (): void {
    $ride = CoordinateLine::ride([[-180.0, -90.0], [180.0, 90.0]]);
    $optional = CoordinateLine::optional([[4.9, 51.1]]);

    expect(CoordinateLine::optional([]))->toBeNull()
        ->and($ride->points)->toBe([[-180.0, -90.0], [180.0, 90.0]])
        ->and($optional)->toBeInstanceOf(CoordinateLine::class)
        ->and($optional->points)->toBe([[4.9, 51.1]]);
});

it('rejects a ride that is too short or a coordinate off the map', function (string $kind): void {
    $build = match ($kind) {
        'empty' => fn (): CoordinateLine => CoordinateLine::ride([]),
        'one point' => fn (): CoordinateLine => CoordinateLine::ride([[4.9, 51.1]]),
        'longitude low' => fn (): ?CoordinateLine => CoordinateLine::optional([[-180.1, 0.0]]),
        'longitude high' => fn (): ?CoordinateLine => CoordinateLine::optional([[180.1, 0.0]]),
        'latitude low' => fn (): ?CoordinateLine => CoordinateLine::optional([[0.0, -90.1]]),
        'latitude high' => fn (): ?CoordinateLine => CoordinateLine::optional([[0.0, 90.1]]),
    };

    expect($build)->toThrow(InvalidArgumentException::class);
})->with([
    'empty',
    'one point',
    'longitude low',
    'longitude high',
    'latitude low',
    'latitude high',
]);
