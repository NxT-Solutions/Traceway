<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceNamer;
use App\Integrations\Nominatim\Requests\ReverseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

uses(RefreshDatabase::class);

function namesPlaces(?string $name, bool $down = false): void
{
    app()->instance(PlaceNamer::class, new readonly class($name, $down) implements PlaceNamer
    {
        public function __construct(private ?string $name, private bool $down) {}

        public function nameAt(float $latitude, float $longitude): ?string
        {
            throw_if($this->down, RuntimeException::class, 'The place search did not answer.');

            return $this->name;
        }
    });
}

it('names the place where a rider dropped a pin', function (): void {
    namesPlaces('Oevel');

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.place'), ['latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['name' => 'Oevel']);
});

it('asks the place search about the rounded pin, once, through the real wiring', function (): void {
    Saloon::fake([
        ReverseRequest::class => MockResponse::make(['address' => ['village' => 'Oevel']]),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('routes.place'), ['latitude' => 51.123456, 'longitude' => 4.987654])
        ->assertOk()
        ->assertExactJson(['name' => 'Oevel']);
    $this->travel(5)->seconds();
    $this->actingAs($user)
        ->postJson(route('routes.place'), ['latitude' => 51.1229, 'longitude' => 4.9881])
        ->assertExactJson(['name' => 'Oevel']);

    Saloon::assertSentCount(1);
    Saloon::assertSent(fn (ReverseRequest $reverseRequest, Response $response): bool => $response->getPendingRequest()->query()->get('lat') === 51.123
        && $response->getPendingRequest()->query()->get('lon') === 4.988);
});

it('has no name when the map has none', function (): void {
    namesPlaces(name: null);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.place'), ['latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['name' => null]);
});

it('has no name, and reports why, when the place search is down', function (): void {
    Exceptions::fake();
    namesPlaces(name: null, down: true);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.place'), ['latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['name' => null]);

    Exceptions::assertReported(RuntimeException::class);
});

it('turns away a point that is not on the map', function (): void {
    namesPlaces('Oevel');

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.place'), ['latitude' => 91, 'longitude' => 4.9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('latitude');
});

it('names places only for a signed-in rider', function (): void {
    namesPlaces('Oevel');

    $this->postJson(route('routes.place'), ['latitude' => 51.1, 'longitude' => 4.9])->assertUnauthorized();
});
