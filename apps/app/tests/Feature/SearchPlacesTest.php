<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceKind;
use App\Domain\Routing\PlaceMatch;
use App\Domain\Routing\PlaceSearch;
use App\Integrations\Nominatim\Requests\SearchRequest;
use App\Models\User;
use App\Routes\PlaceSearchBusy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

uses(RefreshDatabase::class);

/**
 * @param  list<PlaceMatch>|Throwable  $answer
 */
function findsPlaces(array|Throwable $answer): void
{
    app()->instance(PlaceSearch::class, new readonly class($answer) implements PlaceSearch
    {
        /**
         * @param  list<PlaceMatch>|Throwable  $answer
         */
        public function __construct(private array|Throwable $answer) {}

        public function search(string $query, float $nearLatitude, float $nearLongitude): array
        {
            throw_if($this->answer instanceof Throwable, $this->answer);

            return $this->answer;
        }
    });
}

it('finds places by name for a rider', function (): void {
    findsPlaces([new PlaceMatch('Westerlo', 'Turnhout, Belgium', 51.0907, 4.9166, PlaceKind::Town)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['places' => [
            ['name' => 'Westerlo', 'area' => 'Turnhout, Belgium', 'latitude' => 51.0907, 'longitude' => 4.9166, 'kind' => 'town'],
        ]]);
});

it('asks the place search once for a search typed twice, through the real wiring', function (): void {
    Saloon::fake([
        SearchRequest::class => MockResponse::make([
            ['lat' => '51.0907', 'lon' => '4.9166', 'name' => 'Westerlo', 'display_name' => 'Westerlo, Turnhout, Antwerp, Belgium'],
        ]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.123, 'longitude' => 4.987])
        ->assertOk()
        ->assertJsonPath('places.0.name', 'Westerlo');
    $this->travel(5)->seconds();
    $this->actingAs($user)
        ->postJson(route('routes.places'), ['query' => 'westerlo ', 'latitude' => 51.11, 'longitude' => 4.99])
        ->assertJsonPath('places.0.area', 'Turnhout, Belgium');

    Saloon::assertSentCount(1);
    Saloon::assertSent(fn (SearchRequest $searchRequest, Response $response): bool => $response->getPendingRequest()->query()->get('q') === 'westerlo'
        && $response->getPendingRequest()->query()->get('viewbox') === '4.5000,50.6000,5.5000,51.6000');
});

it('finds nothing when the map knows no such place', function (): void {
    findsPlaces([]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'Atlantis', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['places' => []]);
});

it('says the search is busy when it has no turn', function (): void {
    Exceptions::fake();
    findsPlaces(PlaceSearchBusy::noTurn());

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Search is busy. Try again in a moment.']);

    Exceptions::assertNothingReported();
});

it('says the search is busy, and reports why, when the place search is down', function (): void {
    Exceptions::fake();
    findsPlaces(new RuntimeException('The place search did not answer.'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertServiceUnavailable();

    Exceptions::assertReported(RuntimeException::class);
});

it('turns away a search that is too short or a point that is not on the map', function (): void {
    findsPlaces([]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'W', 'latitude' => 91, 'longitude' => 4.9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query', 'latitude']);
});

it('lets one rider search thirty times a minute, so all of Traceway keeps its share', function (): void {
    findsPlaces([]);
    $user = User::factory()->create();

    foreach (range(1, 30) as $search) {
        $this->actingAs($user)
            ->postJson(route('routes.places'), ['query' => 'Westerlo '.$search, 'latitude' => 51.1, 'longitude' => 4.9])
            ->assertOk();
    }

    $this->actingAs($user)
        ->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', fn (string $message): bool => preg_match('/^You have used all 30 searches for this minute\. You can search again in \d+ seconds\.$/', $message) === 1);
});

it('reports only the kind of failure, never what the rider typed', function (): void {
    Exceptions::fake();
    findsPlaces(new RuntimeException('cURL error 28: timed out for https://nominatim.test/search?q=Roodborstlaan+3'));

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.places'), ['query' => 'Roodborstlaan 3', 'latitude' => 51.1, 'longitude' => 4.9])
        ->assertServiceUnavailable();

    Exceptions::assertReported(fn (RuntimeException $runtimeException): bool => ! str_contains($runtimeException->getMessage(), 'Roodborstlaan'));
});

it('finds places only for a signed-in rider', function (): void {
    findsPlaces([]);

    $this->postJson(route('routes.places'), ['query' => 'Westerlo', 'latitude' => 51.1, 'longitude' => 4.9])->assertUnauthorized();
});
