<?php

declare(strict_types=1);

use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Opens a pairing as the device, links it as the rider, and collects the token.
 */
function linkDevice(User $user, string $label = 'Edge 840'): string
{
    $pairing = test()->postJson(route('device.pairings.start'))->assertCreated()->json();

    test()->actingAs($user)
        ->post(route('devices.store'), ['code' => strtolower((string) $pairing['code']), 'label' => $label])
        ->assertRedirect(route('devices.edit'));

    auth()->logout();

    return (string) test()->postJson(route('device.pairings.claim'), ['secret' => $pairing['secret']])
        ->assertOk()
        ->json('token');
}

it('pairs a device with a code and hands the token over once', function (): void {
    $user = User::factory()->create();

    $pairing = $this->postJson(route('device.pairings.start'))
        ->assertCreated()
        ->assertJsonStructure(['code', 'secret', 'expires_in'])
        ->json();

    expect($pairing['code'])->toMatch('/^[A-HJKMNP-Z2-9]{6}$/');

    $this->postJson(route('device.pairings.claim'), ['secret' => $pairing['secret']])
        ->assertAccepted()
        ->assertJson(['status' => 'waiting']);

    $this->actingAs($user)
        ->post(route('devices.store'), ['code' => $pairing['code'], 'label' => 'Edge 840'])
        ->assertRedirect(route('devices.edit'));

    auth()->logout();

    $token = $this->postJson(route('device.pairings.claim'), ['secret' => $pairing['secret']])
        ->assertOk()
        ->json('token');

    expect($token)->toBeString()->toHaveLength(64)
        ->and(DB::table('device_links')->value('token_hash'))->toBe(hash('sha256', (string) $token));

    $this->postJson(route('device.pairings.claim'), ['secret' => $pairing['secret']])->assertGone();
});

it('refuses a code that ran out or was already used', function (): void {
    $user = User::factory()->create();
    $pairing = $this->postJson(route('device.pairings.start'))->json();

    $this->travel(11)->minutes();

    $this->actingAs($user)
        ->from(route('devices.edit'))
        ->post(route('devices.store'), ['code' => $pairing['code'], 'label' => 'Edge'])
        ->assertSessionHasErrors('code');

    $this->postJson(route('device.pairings.claim'), ['secret' => $pairing['secret']])->assertGone();
});

it('refuses a code that is not six letters and digits', function (string $code): void {
    $this->actingAs(User::factory()->create())
        ->from(route('devices.edit'))
        ->post(route('devices.store'), ['code' => $code, 'label' => 'Edge'])
        ->assertSessionHasErrors('code');
})->with(['too short' => 'ABC12', 'ambiguous letter' => 'ABCDE0', 'symbol' => 'ABC!23']);

it('lists only the routes of the rider who linked the device, with device names', function (): void {
    $user = User::factory()->create();
    SavedRoute::factory()->for($user)->create(['name' => 'A very long morning loop', 'sport' => 'road']);
    SavedRoute::factory()->create(['name' => 'Someone else']);

    $token = linkDevice($user);

    $this->withToken($token)
        ->getJson(route('device.routes'))
        ->assertOk()
        ->assertJsonCount(1, 'routes')
        ->assertJsonPath('routes.0.title', 'A very long morning loop')
        ->assertJsonPath('routes.0.name', 'A very long mor')
        ->assertJsonPath('routes.0.sport', 'road')
        ->assertJsonPath('routes.0.legs', []);
});

it('downloads a FIT course for an own route and hides other routes', function (): void {
    $user = User::factory()->create();
    $own = SavedRoute::factory()->for($user)->create(['name' => 'Morning loop']);
    $foreign = SavedRoute::factory()->create();

    $token = linkDevice($user);

    $body = $this->withToken($token)
        ->get(route('device.routes.course', ['savedRoute' => $own->id, 'format' => 'fit']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->getContent();

    expect(substr((string) $body, 8, 4))->toBe('.FIT')
        ->and((string) $body)->toContain('Morning loop');

    $this->withToken($token)
        ->getJson(route('device.routes.course', ['savedRoute' => $foreign->id, 'format' => 'fit']))
        ->assertNotFound();
});

it('downloads each land leg of a multisport plan and no swim', function (): void {
    $user = User::factory()->create();
    $route = SavedRoute::factory()->for($user)->create([
        'name' => 'Brick',
        'legs' => [
            ['discipline' => 'swim', 'distance_metres' => 750, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11]]],
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 0, 'coordinates' => [[4.9, 51.1], [4.91, 51.11], [4.9, 51.1]]],
        ],
    ]);

    $token = linkDevice($user);

    $this->withToken($token)
        ->getJson(route('device.routes'))
        ->assertJsonPath('routes.0.legs', [['n' => 2, 'name' => 'Brick 2 Road', 'sport' => 'road']]);

    $body = $this->withToken($token)
        ->get(route('device.routes.course.leg', ['savedRoute' => $route->id, 'format' => 'fit', 'leg' => 2]))
        ->assertOk()
        ->getContent();

    expect((string) $body)->toContain('Brick 2 Road');

    $this->withToken($token)
        ->getJson(route('device.routes.course.leg', ['savedRoute' => $route->id, 'format' => 'fit', 'leg' => 1]))
        ->assertNotFound();
});

it('downloads the same course as GPX for a device that reads GPX', function (): void {
    $user = User::factory()->create();
    $route = SavedRoute::factory()->for($user)->create(['name' => 'Morning loop']);

    $token = linkDevice($user);

    $this->withToken($token)
        ->get(route('device.routes.course', ['savedRoute' => $route->id, 'format' => 'gpx']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/gpx+xml')
        ->assertSee('<gpx', false)
        ->assertSee('Morning loop', false);

    $this->withToken($token)
        ->get('/api/device/routes/'.$route->id.'/tcx')
        ->assertNotFound();
});

it('records which provider opened the pairing and refuses an unknown one', function (): void {
    $this->postJson(route('device.pairings.start'), ['provider' => 'garmin'])->assertCreated();

    expect(DB::table('device_links')->value('provider'))->toBe('garmin');

    $this->postJson(route('device.pairings.start'), ['provider' => 'nokia'])->assertUnprocessable();
});

it('turns a device away without a token, with a wrong one, or after it was removed', function (): void {
    $user = User::factory()->create();
    $token = linkDevice($user);

    $this->getJson(route('device.routes'))->assertUnauthorized();
    $this->withToken(str_repeat('x', 64))->getJson(route('device.routes'))->assertUnauthorized();

    $this->withToken($token)->getJson(route('device.routes'))->assertOk();

    $id = (string) DB::table('device_links')->value('id');

    $this->actingAs(User::factory()->create())
        ->delete(route('devices.destroy', ['deviceLink' => $id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('devices.destroy', ['deviceLink' => $id]))
        ->assertRedirect(route('devices.edit'));

    auth()->logout();

    $this->withToken($token)->getJson(route('device.routes'))->assertUnauthorized();
});

it('shows linked devices on the settings page', function (): void {
    $user = User::factory()->create();
    linkDevice($user, 'Fenix 8');

    $this->postJson(route('device.pairings.start'))->assertCreated();

    $this->actingAs($user)
        ->get(route('devices.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('settings/devices')
            ->has('devices', 1)
            ->where('devices.0.label', 'Fenix 8')
            ->where('devices.0.provider', 'Garmin'));
});

it('limits how often one address opens a pairing', function (): void {
    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('device.pairings.start'))->assertCreated();
    }

    $this->postJson(route('device.pairings.start'))->assertTooManyRequests();
});
