<?php

declare(strict_types=1);

use App\Domain\Routing\Place;
use App\Domain\Routing\PlaceFinder;
use App\Routes\PacedPlaceFinder;
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

function fixedPlaceFinder(bool $down = false): PlaceFinder
{
    return new readonly class($down) implements PlaceFinder
    {
        public function __construct(private bool $down) {}

        public function find(string $search): Place
        {
            throw_if($this->down, RuntimeException::class, 'The place search did not answer.');

            return new Place('Westerlo, Belgium', 51.09, 4.92);
        }
    };
}

it('waits for its turn when the planner just asked the place search', function (): void {
    $placeSearchPace = new PlaceSearchPace(new Repository(new ArrayStore));
    $placeSearchPace->take();

    $place = new PacedPlaceFinder(fixedPlaceFinder(), $placeSearchPace)->find('Westerlo');

    expect($place?->name)->toBe('Westerlo, Belgium')
        ->and(Date::now()->toTimeString())->toBe('12:00:02');
});

it('asks straight away when it is Traceway’s turn', function (): void {
    new PacedPlaceFinder(fixedPlaceFinder(), new PlaceSearchPace(new Repository(new ArrayStore)))->find('Westerlo');

    Sleep::assertNeverSlept();
});

it('still finds the start of a plan while the planner rests', function (): void {
    $placeSearchPace = new PlaceSearchPace(new Repository(new ArrayStore));
    $placeSearchPace->rest();

    expect(new PacedPlaceFinder(fixedPlaceFinder(), $placeSearchPace)->find('Westerlo')?->name)->toBe('Westerlo, Belgium');
});

it('makes the planner rest when the place search fails', function (): void {
    $placeSearchPace = new PlaceSearchPace(new Repository(new ArrayStore));
    $paced = new PacedPlaceFinder(fixedPlaceFinder(down: true), $placeSearchPace);

    expect(fn (): ?Place => $paced->find('Westerlo'))->toThrow(RuntimeException::class);

    Date::setTestNow('2026-10-14 12:00:10');

    expect($placeSearchPace->take())->toBeFalse();
});
