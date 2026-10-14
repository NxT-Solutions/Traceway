<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceNamer;
use App\Routes\CachedPlaceNamer;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

function countingPlaceNamer(?string $name): PlaceNamer
{
    return new class($name) implements PlaceNamer
    {
        /** @var list<array{0: float, 1: float}> */
        public array $asked = [];

        public function __construct(private readonly ?string $name) {}

        public function nameAt(float $latitude, float $longitude): ?string
        {
            $this->asked[] = [$latitude, $longitude];

            return $this->name;
        }
    };
}

it('asks about a point rounded to about a hundred metres, not the exact pin', function (): void {
    $placeNamer = countingPlaceNamer('Oevel');

    $name = new CachedPlaceNamer($placeNamer, new Repository(new ArrayStore))->nameAt(51.123456, 4.987654);

    expect($name)->toBe('Oevel')
        ->and($placeNamer->asked)->toBe([[51.123, 4.988]]);
});

it('asks once for pins dropped close together', function (): void {
    $placeNamer = countingPlaceNamer('Oevel');
    $cachedPlaceNamer = new CachedPlaceNamer($placeNamer, new Repository(new ArrayStore));

    $cachedPlaceNamer->nameAt(51.1231, 4.9871);
    $cachedPlaceNamer->nameAt(51.1229, 4.9869);
    $cachedPlaceNamer->nameAt(51.2, 4.9);

    expect($placeNamer->asked)->toHaveCount(2);
});

it('asks again about a point the map has no name for', function (): void {
    $placeNamer = countingPlaceNamer(name: null);
    $cachedPlaceNamer = new CachedPlaceNamer($placeNamer, new Repository(new ArrayStore));

    $cachedPlaceNamer->nameAt(52.0, 2.5);
    $cachedPlaceNamer->nameAt(52.0, 2.5);

    expect($placeNamer->asked)->toHaveCount(2);
});
