<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceNamer;
use App\Routes\CachedPlaceNamer;
use App\Routes\PlaceSearchPace;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Date::setTestNow('2026-10-14 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

afterEach(function (): void {
    Sleep::fake(value: false);
    Date::setTestNow();
});

function countingPlaceNamer(?string $name, bool $down = false): PlaceNamer
{
    return new class($name, $down) implements PlaceNamer
    {
        /** @var list<array{0: float, 1: float}> */
        public array $asked = [];

        public function __construct(private readonly ?string $name, private readonly bool $down) {}

        public function nameAt(float $latitude, float $longitude): ?string
        {
            $this->asked[] = [$latitude, $longitude];

            throw_if($this->down, RuntimeException::class, 'The place search did not answer.');

            return $this->name;
        }
    };
}

function cachedPlaceNamer(PlaceNamer $placeNamer): CachedPlaceNamer
{
    $repository = new Repository(new ArrayStore);

    return new CachedPlaceNamer($placeNamer, $repository, new PlaceSearchPace($repository));
}

it('asks about a point rounded to about a hundred metres, not the exact pin', function (): void {
    $placeNamer = countingPlaceNamer('Oevel');

    $name = cachedPlaceNamer($placeNamer)->nameAt(51.123456, 4.987654);

    expect($name)->toBe('Oevel')
        ->and($placeNamer->asked)->toBe([[51.123, 4.988]]);
});

it('asks once for pins dropped close together', function (): void {
    $placeNamer = countingPlaceNamer('Oevel');
    $cachedPlaceNamer = cachedPlaceNamer($placeNamer);

    $cachedPlaceNamer->nameAt(51.1231, 4.9871);
    $cachedPlaceNamer->nameAt(51.1229, 4.9869);
    $cachedPlaceNamer->nameAt(51.2, 4.9);

    expect($placeNamer->asked)->toHaveCount(2);
});

it('asks again about a point the map has no name for', function (): void {
    $placeNamer = countingPlaceNamer(name: null);
    $cachedPlaceNamer = cachedPlaceNamer($placeNamer);

    $cachedPlaceNamer->nameAt(52.0, 2.5);
    $cachedPlaceNamer->nameAt(52.0, 2.5);

    expect($placeNamer->asked)->toHaveCount(2);
});

it('has a pin tapped straight after another wait for its turn, then names it', function (): void {
    $placeNamer = countingPlaceNamer('Oevel');
    $cachedPlaceNamer = cachedPlaceNamer($placeNamer);

    $cachedPlaceNamer->nameAt(51.1, 4.9);

    expect($cachedPlaceNamer->nameAt(51.2, 4.8))->toBe('Oevel')
        ->and($placeNamer->asked)->toHaveCount(2)
        ->and(Date::now()->toTimeString())->toBe('12:00:02');
});

it('stops asking for a minute after the place search fails', function (): void {
    $placeNamer = countingPlaceNamer('Oevel', down: true);
    $cachedPlaceNamer = cachedPlaceNamer($placeNamer);

    expect(fn (): ?string => $cachedPlaceNamer->nameAt(51.1, 4.9))->toThrow(RuntimeException::class);

    Date::setTestNow('2026-10-14 12:00:30');

    expect($cachedPlaceNamer->nameAt(51.2, 4.8))->toBeNull()
        ->and($placeNamer->asked)->toHaveCount(1);
    Sleep::assertNeverSlept();
});
