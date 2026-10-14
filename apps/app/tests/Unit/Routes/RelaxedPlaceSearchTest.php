<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use App\Routes\PlaceSearchBusy;
use App\Routes\RelaxedPlaceSearch;

/**
 * @param  array<string, list<PlaceMatch>>  $answers
 */
function placeSearchAnswering(array $answers): PlaceSearch
{
    return new class($answers) implements PlaceSearch
    {
        /** @var list<string> */
        public array $asked = [];

        /**
         * @param  array<string, list<PlaceMatch>>  $answers
         */
        public function __construct(private readonly array $answers) {}

        /**
         * @return list<PlaceMatch>
         */
        public function search(string $query, float $nearLatitude, float $nearLongitude): array
        {
            $this->asked[] = $query;

            throw_if($query === 'busy', PlaceSearchBusy::noTurn());

            return $this->answers[$query] ?? [];
        }
    };
}

it('asks once more without the last word when a search finds nothing', function (): void {
    $street = new PlaceMatch('Roodborstlaan 3', 'Hoboken, Belgium', 51.17, 4.35);
    $placeSearch = placeSearchAnswering(['Roodborstlaan 3' => [$street]]);

    $matches = new RelaxedPlaceSearch($placeSearch)->search('Roodborstlaan 3, Hobokne', 51.1, 4.9);

    expect($matches)->toBe([$street])
        ->and($placeSearch->asked)->toBe(['Roodborstlaan 3, Hobokne', 'Roodborstlaan 3']);
});

it('asks only once when the search finds something', function (): void {
    $westerlo = new PlaceMatch('Westerlo', 'Belgium', 51.09, 4.91);
    $placeSearch = placeSearchAnswering(['Westerlo Belgium' => [$westerlo]]);

    expect(new RelaxedPlaceSearch($placeSearch)->search('Westerlo Belgium', 51.1, 4.9))->toBe([$westerlo])
        ->and($placeSearch->asked)->toHaveCount(1);
});

it('finds nothing, rather than busy, when the second try gets no turn', function (): void {
    $placeSearch = placeSearchAnswering([]);

    expect(new RelaxedPlaceSearch($placeSearch)->search('busy Hobokne', 51.1, 4.9))->toBe([])
        ->and($placeSearch->asked)->toBe(['busy Hobokne', 'busy']);
});

it('does not shorten a single word', function (): void {
    $placeSearch = placeSearchAnswering([]);

    expect(new RelaxedPlaceSearch($placeSearch)->search('Atlantis', 51.1, 4.9))->toBe([])
        ->and($placeSearch->asked)->toBe(['Atlantis']);
});
