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

it('saves a route on the rider profile and gives it a public link', function (): void {
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
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('saved')
            ->where('routes.0.name', 'Morning loop')
            ->where('routes.0.url', URL::signedRoute('routes.share', $route))
            ->where('routes.0.fit_url', URL::signedRoute('routes.share.fit', $route))
            ->has('routes.0.coordinates', 3));

    $this->actingAs(User::factory()->create())
        ->get(route('routes.library'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('saved')
            ->has('routes', 0));
});

it('shows a saved route to anyone with the link', function (): void {
    $route = SavedRoute::factory()->create();

    $this->get(route('routes.share', $route))
        ->assertForbidden();

    $this->get(URL::signedRoute('routes.share', $route))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('share')
            ->where('route.name', 'Morning loop')
            ->where('route.distance_metres', 20_000)
            ->has('route.coordinates', 3)
            ->missing('route.user')
            ->missing('route.finish_coordinates')
            ->missing('route.user_coordinates'));
});

it('gives a signed link a gpx and a fit file', function (): void {
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

it('lets the owner remove a saved route', function (): void {
    $route = SavedRoute::factory()->create();

    $this->actingAs($route->user)
        ->delete(route('routes.saved.destroy', $route))
        ->assertRedirect(route('routes.library'));

    expect(SavedRoute::query()->find($route->id))->toBeNull();
});

it('does not let another rider remove a saved route', function (): void {
    $route = SavedRoute::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('routes.saved.destroy', $route))
        ->assertForbidden();

    expect(SavedRoute::query()->find($route->id))->not->toBeNull();
});

it('asks a guest to log in before opening saved routes', function (): void {
    $this->get(route('routes.library'))->assertRedirect();
});

it('asks a guest to log in before saving a route', function (): void {
    $this->postJson(route('routes.saved.store'), savedRoutePayload())
        ->assertUnauthorized();
});

/**
 * @return array<string, mixed>
 */
function multisportPayload(): array
{
    return [
        ...savedRoutePayload(),
        'name' => 'Sunday triathlon',
        'sport' => 'road',
        'layout' => 'loops',
        'user_coordinates' => null,
        'finish_coordinates' => null,
        'legs' => [
            ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.89, 51.1], [4.895, 51.103]]],
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 40, 'coordinates' => [[4.9, 51.1], [4.93, 51.12], [4.9, 51.1]], 'color' => '#DB2777'],
            ['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 10, 'coordinates' => [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]],
        ],
    ];
}

it('saves a multisport plan with its legs and shares a course for each land leg', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('routes.saved.store'), multisportPayload())
        ->assertOk();

    $savedRoute = SavedRoute::query()->firstOrFail();

    expect($savedRoute->layout)->toBe('loops')
        ->and($savedRoute->legs)->toHaveCount(3)
        ->and($savedRoute->legs[0]['discipline'] ?? null)->toBe('swim');

    $this->actingAs($user)
        ->get(route('routes.library'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('saved')
            ->where('routes.0.layout', 'loops')
            ->where('routes.0.legs', [
                ['discipline' => 'swim', 'distance_metres' => 750, 'fit_url' => null],
                ['discipline' => 'road', 'distance_metres' => 20_000, 'fit_url' => URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $savedRoute->id, 'leg' => 2])],
                ['discipline' => 'run', 'distance_metres' => 5_000, 'fit_url' => URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $savedRoute->id, 'leg' => 3])],
            ]));

    $this->get($savedRoute->shareUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('share')
            ->where('route.layout', 'loops')
            ->where('route.legs.0.fit_url', expected: null)
            ->where('route.legs.0.color', expected: null)
            ->where('route.legs.1.color', '#db2777')
            ->where('route.legs.1.fit_url', URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $savedRoute->id, 'leg' => 2]))
            ->where('route.legs.2.coordinates', [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]));

    $fit = $this->get(URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $savedRoute->id, 'leg' => 3]));

    $fit->assertOk()->assertHeader('content-disposition', 'attachment; filename="sunday-tr-3-run.fit"');

    expect($fit->getContent())->toContain('Sunday tr 3 Run');

    $gpx = $this->get($savedRoute->gpxUrl())->assertOk()->getContent();

    expect($gpx)->toBeString()
        ->and(substr_count((string) $gpx, '<trk>'))->toBe(3)
        ->and($gpx)->toContain('<name>Swim</name><type>swim</type>')
        ->and($gpx)->toContain('<name>Sunday t 2 Road</name><type>road</type>');
});

it('has no course for the swim or a leg past the end', function (int $leg): void {
    $route = SavedRoute::factory()->create([
        'layout' => 'loops',
        'legs' => multisportPayload()['legs'],
    ]);

    $this->get(URL::signedRoute('routes.share.fit.leg', ['savedRoute' => $route->id, 'leg' => $leg]))
        ->assertNotFound();
})->with([
    'swim' => [1],
    'past the end' => [4],
]);

it('refuses a multisport plan with a land leg of one point or no layout', function (array $change): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.saved.store'), [...multisportPayload(), ...$change])
        ->assertUnprocessable();
})->with([
    'one point land leg' => [['legs' => [['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1]]]]]],
    'no layout' => [['layout' => null]],
    'unknown discipline' => [['legs' => [['discipline' => 'rowing', 'distance_metres' => 5_000, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11]]]]]],
    'colour that is not a hex colour' => [['legs' => [['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11]], 'color' => 'orange']]]],
]);

it('keeps a single-sport route without legs', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('routes.saved.store'), savedRoutePayload())
        ->assertOk();

    $savedRoute = SavedRoute::query()->firstOrFail();

    expect($savedRoute->layout)->toBeNull()
        ->and($savedRoute->legs)->toBeNull();

    $this->get($savedRoute->shareUrl())
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('route.layout', expected: null)
            ->where('route.legs', []));
});
