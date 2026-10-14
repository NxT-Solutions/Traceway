<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceKind;
use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use App\Routes\CachedPlaceSearch;
use App\Routes\PlaceSearchBusy;
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

function countingPlaceSearch(bool $down = false): PlaceSearch
{
    return new class($down) implements PlaceSearch
    {
        /** @var list<array{0: string, 1: float, 2: float}> */
        public array $asked = [];

        public function __construct(private readonly bool $down) {}

        /**
         * @return list<PlaceMatch>
         */
        public function search(string $query, float $nearLatitude, float $nearLongitude): array
        {
            $this->asked[] = [$query, $nearLatitude, $nearLongitude];

            throw_if($this->down, RuntimeException::class, 'The place search did not answer.');

            return [new PlaceMatch('Westerlo', 'Turnhout, Belgium', 51.0907, 4.9166, PlaceKind::Town)];
        }
    };
}

function cachedPlaceSearch(PlaceSearch $placeSearch, ?Repository $repository = null): CachedPlaceSearch
{
    $repository ??= new Repository(new ArrayStore);

    return new CachedPlaceSearch($placeSearch, $repository, new PlaceSearchPace($repository));
}

it('searches near the map rounded to about ten kilometres, not where the rider looks', function (): void {
    $placeSearch = countingPlaceSearch();

    $matches = cachedPlaceSearch($placeSearch)->search('Westerlo', 51.123456, 4.987654);

    expect($matches)->toEqual([new PlaceMatch('Westerlo', 'Turnhout, Belgium', 51.0907, 4.9166, PlaceKind::Town)])
        ->and($placeSearch->asked)->toBe([['westerlo', 51.1, 5.0]]);
});

it('asks once for the same words typed differently near the same place', function (): void {
    $placeSearch = countingPlaceSearch();
    $cachedPlaceSearch = cachedPlaceSearch($placeSearch);

    $cachedPlaceSearch->search('Westerlo', 51.11, 4.98);

    $first = $cachedPlaceSearch->search('  WESTERLO ', 51.12, 5.01);
    $cachedPlaceSearch->search('Westerlo', 50.5, 4.98);

    expect($first)->toEqual([new PlaceMatch('Westerlo', 'Turnhout, Belgium', 51.0907, 4.9166, PlaceKind::Town)])
        ->and($placeSearch->asked)->toHaveCount(2);
});

it('is busy, without asking, when another search has the turn', function (): void {
    $placeSearch = countingPlaceSearch();
    $repository = new Repository(new ArrayStore);
    new PlaceSearchPace($repository)->rest();

    expect(fn (): array => cachedPlaceSearch($placeSearch, $repository)->search('Westerlo', 51.1, 4.9))->toThrow(PlaceSearchBusy::class)
        ->and($placeSearch->asked)->toBe([]);
});

it('stops asking for a minute after the place search fails', function (): void {
    $placeSearch = countingPlaceSearch(down: true);
    $cachedPlaceSearch = cachedPlaceSearch($placeSearch);

    expect(fn (): array => $cachedPlaceSearch->search('Westerlo', 51.1, 4.9))->toThrow(RuntimeException::class);

    Date::setTestNow('2026-10-14 12:00:30');

    expect(fn (): array => $cachedPlaceSearch->search('Oevel', 51.1, 4.9))->toThrow(PlaceSearchBusy::class)
        ->and($placeSearch->asked)->toHaveCount(1);
});
