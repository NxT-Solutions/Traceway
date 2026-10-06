<?php

declare(strict_types=1);

use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function savedRoutePayload(): array
{
    return [
        'name' => 'Morning loop',
        'sport' => 'road',
        'surface' => 'paved',
        'elevation' => 'neutral',
        'roads' => 'quiet',
        'distance_metres' => 9600,
        'ascent_metres' => 40,
        'coordinates' => [
            [4.9, 51.1],
            [4.93, 51.12],
            [4.9, 51.1],
        ],
        'user_coordinates' => [
            [4.9, 51.1],
            [4.93, 51.12],
        ],
        'finish_coordinates' => [
            [4.93, 51.12],
            [4.9, 51.1],
        ],
    ];
}

it('saves a route on the rider profile and gives it a public link', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('routes.saved.store'), savedRoutePayload())
        ->assertOk();

    $route = SavedRoute::query()->first();

    expect($route)->not->toBeNull()
        ->and($route->user_id)->toBe($user->id)
        ->and($route->name)->toBe('Morning loop')
        ->and($response->json('url'))->toBe(URL::signedRoute('routes.share', $route));

    $this->actingAs($user)
        ->get(route('routes.library'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('saved')
            ->where('routes.0.name', 'Morning loop')
            ->where('routes.0.url', URL::signedRoute('routes.share', $route))
            ->has('routes.0.coordinates', 3));

    $this->actingAs(User::factory()->create())
        ->get(route('routes.library'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('saved')
            ->has('routes', 0));
});

it('shows a saved route to anyone with the link', function () {
    $route = SavedRoute::factory()->create();

    $this->get(route('routes.share', $route))
        ->assertForbidden();

    $this->get(URL::signedRoute('routes.share', $route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('share')
            ->where('route.name', 'Morning loop')
            ->where('route.distance_metres', 20_000)
            ->has('route.coordinates', 3)
            ->missing('route.user')
            ->missing('route.finish_coordinates')
            ->missing('route.user_coordinates'));
});

it('gives a signed link a gpx and a fit file', function () {
    $route = SavedRoute::factory()->create(['name' => 'Morning loop']);

    $this->get(route('routes.share.gpx', $route))->assertForbidden();
    $this->get(route('routes.share.fit', $route))->assertForbidden();

    $this->get(URL::signedRoute('routes.share.gpx', $route))
        ->assertOk()
        ->assertHeader('content-type', 'application/gpx+xml')
        ->assertSee('<trkpt lat="51.05" lon="3.72">', false);

    $fit = $this->get(URL::signedRoute('routes.share.fit', $route));
    $fit->assertOk();
    expect($fit->headers->get('content-disposition'))->toContain('morning-loop.fit')
        ->and(strlen($fit->getContent() ?: ''))->toBeGreaterThan(20);
});

it('lets the owner remove a saved route', function () {
    $route = SavedRoute::factory()->create();

    $this->actingAs($route->user)
        ->delete(route('routes.saved.destroy', $route))
        ->assertRedirect(route('routes.library'));

    expect(SavedRoute::query()->find($route->id))->toBeNull();
});

it('does not let another rider remove a saved route', function () {
    $route = SavedRoute::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('routes.saved.destroy', $route))
        ->assertForbidden();

    expect(SavedRoute::query()->find($route->id))->not->toBeNull();
});

it('asks a guest to log in before opening saved routes', function () {
    $this->get(route('routes.library'))->assertRedirect();
});

it('asks a guest to log in before saving a route', function () {
    $this->postJson(route('routes.saved.store'), savedRoutePayload())
        ->assertUnauthorized();
});
