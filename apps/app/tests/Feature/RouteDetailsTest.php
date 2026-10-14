<?php

declare(strict_types=1);

use App\Domain\Routing\Details\RouteDetailsSource;
use App\Domain\Routing\Sport;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FixedRouteDetails;
use Tests\Support\FitMessages;

uses(RefreshDatabase::class);

/**
 * @return list<array{0: float, 1: float}>
 */
function detailsLine(): array
{
    return [[4.9, 51.1], [4.93, 51.12], [4.9, 51.1]];
}

it('reads the climb, surfaces, turns, and stops of a line', function (): void {
    app()->instance(RouteDetailsSource::class, new FixedRouteDetails);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.details'), ['sport' => 'gravel', 'coordinates' => detailsLine()])
        ->assertOk()
        ->assertJsonPath('ascent_metres', 40)
        ->assertJsonPath('descent_metres', 40)
        ->assertJsonPath('profile.1.1', 50)
        ->assertJsonPath('surfaces.0.surface', 'paved')
        ->assertJsonPath('cues.0.turn', 'left')
        ->assertJsonPath('cues.0.street', 'Mill Road')
        ->assertJsonCount(0, 'stops');
});

it('finds the stops along a line on their own, since they can take longer', function (): void {
    app()->instance(RouteDetailsSource::class, new FixedRouteDetails);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.stops'), ['sport' => 'walk', 'coordinates' => detailsLine()])
        ->assertOk()
        ->assertJsonPath('stops.0.kind', 'water')
        ->assertJsonPath('stops.0.name', null);
});

it('says so when the stops do not load', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.stops'), ['sport' => 'walk', 'coordinates' => detailsLine()])
        ->assertStatus(502)
        ->assertJsonPath('message', 'Water and cafés did not load. The route still works.');
});

it('says so when the map server does not answer', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.details'), ['sport' => 'road', 'coordinates' => detailsLine()])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The climb and the turns did not load. The route still works.');
});

it('refuses a line it cannot read', function (array $payload): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.details'), $payload)
        ->assertUnprocessable();
})->with([
    'one point' => [['sport' => 'road', 'coordinates' => [[4.9, 51.1]]]],
    'no sport' => [['coordinates' => [[4.9, 51.1], [4.91, 51.1]]]],
    'off the map' => [['sport' => 'road', 'coordinates' => [[4.9, 91.0], [4.91, 51.1]]]],
]);

it('asks a guest to log in first', function (): void {
    $this->postJson(route('routes.details'), [])->assertUnauthorized();
});

it('keeps the details with a saved route and puts them in its course files', function (): void {
    app()->instance(RouteDetailsSource::class, new FixedRouteDetails);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.store'), [
            'name' => 'Morning loop',
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'roads' => 'quiet',
            'distance_metres' => 9600,
            'ascent_metres' => 0,
            'coordinates' => detailsLine(),
        ])
        ->assertOk();

    $savedRoute = SavedRoute::query()->firstOrFail();

    expect($savedRoute->ascent_metres)->toBe(40)
        ->and($savedRoute->details['cues'] ?? null)->toHaveCount(1);

    $fit = (string) $this->get(URL::signedRoute('routes.share.fit', $savedRoute))->assertOk()->getContent();
    $points = FitMessages::of($fit, 32);

    expect(array_map(fn (array $point): mixed => $point[5], $points))->toBe([3, 6])
        ->and(FitMessages::of($fit, 20)[0])->toHaveKey(2);

    $this->get(URL::signedRoute('routes.share.gpx', $savedRoute))
        ->assertOk()
        ->assertSee('<sym>Drinking Water</sym>', false)
        ->assertSee('<ele>10</ele>', false);
});

it('still saves a route when the map server does not answer', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.saved.store'), [
            'name' => 'Morning loop',
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'roads' => 'quiet',
            'distance_metres' => 9600,
            'ascent_metres' => 25,
            'coordinates' => detailsLine(),
        ])
        ->assertOk();

    $savedRoute = SavedRoute::query()->firstOrFail();

    expect($savedRoute->details)->toBeNull()
        ->and($savedRoute->ascent_metres)->toBe(25);
});

it('reads each land leg of a multisport plan and adds up their climbs', function (): void {
    $fixedRouteDetails = new FixedRouteDetails;
    app()->instance(RouteDetailsSource::class, $fixedRouteDetails);

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.saved.store'), [
            'name' => 'Sunday brick',
            'sport' => 'road',
            'surface' => 'paved',
            'elevation' => 'neutral',
            'roads' => 'quiet',
            'distance_metres' => 25_750,
            'ascent_metres' => 0,
            'coordinates' => detailsLine(),
            'layout' => 'loops',
            'legs' => [
                ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.89, 51.1], [4.895, 51.103]]],
                ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 0, 'coordinates' => detailsLine()],
                ['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]],
            ],
        ])
        ->assertOk();

    $savedRoute = SavedRoute::query()->firstOrFail();
    $legs = $savedRoute->legs ?? [];

    expect($fixedRouteDetails->asked)->toHaveCount(2)
        ->and($savedRoute->ascent_metres)->toBe(80)
        ->and($savedRoute->details)->toBeNull()
        ->and($legs[0]['details'] ?? null)->toBeNull()
        ->and($legs[1]['ascent_metres'] ?? null)->toBe(40)
        ->and($legs[2]['details']['stops'] ?? null)->toHaveCount(1);

    $fit = (string) $this->get(URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $savedRoute->id, 'leg' => 3]))->assertOk()->getContent();

    expect(FitMessages::of($fit, 32))->toHaveCount(2);
});

it('puts the turns into a course downloaded from the planner', function (): void {
    app()->instance(RouteDetailsSource::class, new FixedRouteDetails);

    $fit = (string) $this->actingAs(User::factory()->create())
        ->post(route('routes.fit'), ['name' => 'Morning loop', 'sport' => 'road', 'coordinates' => detailsLine()])
        ->assertOk()
        ->getContent();

    expect(FitMessages::of($fit, 32))->toHaveCount(2);
});

it('gives the owner the details kept with a saved route or one of its legs', function (): void {
    app()->instance(RouteDetailsSource::class, new FixedRouteDetails);
    $user = User::factory()->create();
    $savedRoute = SavedRoute::factory()->for($user)->create([
        'details' => new FixedRouteDetails()->for(Sport::Road, detailsLine())->toArray(),
    ]);

    $this->actingAs($user)
        ->getJson(route('routes.saved.details', $savedRoute))
        ->assertOk()
        ->assertJsonPath('details.ascent_metres', 40)
        ->assertJsonPath('details.cues.0.street', 'Mill Road');

    $this->actingAs(User::factory()->create())
        ->getJson(route('routes.saved.details', $savedRoute))
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('routes.saved.details', SavedRoute::factory()->for($user)->create()))
        ->assertOk()
        ->assertJsonPath('details', null);
});

it('shows the climb and the stops on a shared route', function (): void {
    $savedRoute = SavedRoute::factory()->create([
        'details' => new FixedRouteDetails()->for(Sport::Road, detailsLine())->toArray(),
    ]);

    $this->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('share')
            ->where('route.details.ascent_metres', 40)
            ->has('route.details.stops', 1));
});

it('reads the details of routes saved before they were kept', function (): void {
    $fixedRouteDetails = new FixedRouteDetails;
    app()->instance(RouteDetailsSource::class, $fixedRouteDetails);
    $older = SavedRoute::factory()->create(['ascent_metres' => 0]);
    SavedRoute::factory()->create(['details' => $fixedRouteDetails->for(Sport::Road, detailsLine())->toArray()]);
    $fixedRouteDetails->asked = [];

    $this->artisan('routes:details')
        ->expectsOutputToContain('1 of 1 routes')
        ->assertSuccessful();

    $older->refresh();

    expect($fixedRouteDetails->asked)->toHaveCount(1)
        ->and($older->ascent_metres)->toBe(40)
        ->and($older->details)->not->toBeNull();
});

it('shows the climb of each land leg on a shared multisport plan', function (): void {
    $legDetails = new FixedRouteDetails()->for(Sport::Run, detailsLine())->toArray();
    $savedRoute = SavedRoute::factory()->create([
        'layout' => 'loops',
        'legs' => [
            ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.89, 51.1], [4.895, 51.103]]],
            ['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 40, 'coordinates' => detailsLine(), 'details' => $legDetails],
        ],
    ]);

    $this->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('route.legs.0.details', expected: null)
            ->where('route.legs.1.details.ascent_metres', 40)
            ->has('route.legs.1.details.cues', 1));
});

it('keeps the planner climb when the heights do not come back', function (): void {
    $fixedRouteDetails = new FixedRouteDetails;
    $fixedRouteDetails->heights = false;

    app()->instance(RouteDetailsSource::class, $fixedRouteDetails);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('routes.saved.store'), [
        'name' => 'Hills without heights',
        'sport' => 'road',
        'surface' => 'paved',
        'elevation' => 'neutral',
        'roads' => 'quiet',
        'distance_metres' => 5_000,
        'ascent_metres' => 450,
        'coordinates' => detailsLine(),
    ])->assertSuccessful();

    expect(SavedRoute::query()->sole()->ascent_metres)->toBe(450);
});

it('refuses a line too long for the map servers', function (): void {
    $line = [[3.0, 51.0], [12.0, 51.0], [3.0, 51.0]];

    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.details'), ['sport' => 'road', 'coordinates' => $line])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coordinates']);
});

it('answers a malformed route id with not found', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/routes/saved/not-a-route/details')
        ->assertNotFound();
});

it('does not let routes that cannot be read hold back the ones that can', function (): void {
    $fixedRouteDetails = new FixedRouteDetails;
    app()->instance(RouteDetailsSource::class, $fixedRouteDetails);
    $legDetails = $fixedRouteDetails->for(Sport::Run, detailsLine())->toArray();
    SavedRoute::factory()->create([
        'layout' => 'loops',
        'legs' => [['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 40, 'coordinates' => detailsLine(), 'details' => $legDetails]],
    ]);
    $failing = SavedRoute::factory()->create();
    $waiting = SavedRoute::factory()->create();
    $fixedRouteDetails->complete = false;

    $this->artisan('routes:details', ['--limit' => 1])->assertSuccessful();

    $fixedRouteDetails->complete = true;

    $this->artisan('routes:details', ['--limit' => 1])
        ->expectsOutputToContain('1 of 1 routes')
        ->assertSuccessful();

    expect($failing->refresh()->details)->toBeNull()
        ->and($waiting->refresh()->details)->not->toBeNull();
});
