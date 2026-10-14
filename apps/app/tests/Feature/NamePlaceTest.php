<?php

declare(strict_types=1);

use App\Domain\Routing\PlaceNamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('has no name when the map has none or the place search is down', function (bool $down): void {
    namesPlaces(name: null, down: $down);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.place'), ['latitude' => 51.1, 'longitude' => 4.9])
        ->assertOk()
        ->assertExactJson(['name' => null]);
})->with(['no name' => false, 'down' => true]);

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
