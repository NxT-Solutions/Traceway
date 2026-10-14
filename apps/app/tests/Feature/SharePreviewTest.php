<?php

declare(strict_types=1);

use App\Account\RiderId;
use App\Contracts\Routes\RouteImageStore;
use App\Models\SavedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
});

function routeImage(int $width = 1200, int $height = 630): UploadedFile
{
    return UploadedFile::fake()->image('route.jpg', $width, $height);
}

it('gives chats a title, a line, and a picture for a shared route, and keeps search engines out', function (): void {
    $savedRoute = SavedRoute::factory()->create(['name' => 'Huy hills', 'distance_metres' => 28_900, 'ascent_metres' => 333]);

    $response = $this->get(URL::signedRoute('routes.share', $savedRoute))->assertOk();
    $html = (string) $response->getContent();

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($html)->toContain('<title>Huy hills · 28.9 km road ride</title>')
        ->and($html)->toContain('<meta name="robots" content="noindex, nofollow">')
        ->and($html)->toContain('<meta property="og:title" content="Huy hills · 28.9 km road ride">')
        ->and($html)->toContain('28.9 km road ride with 333 m of climbing, mostly paved.')
        ->and($html)->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->and($html)->toContain('<meta property="og:image" content="'.e(URL::signedRoute('routes.share.image', ['savedRoute' => $savedRoute->id, 'v' => 0])).'">');
});

it('names each leg of a shared multisport plan', function (): void {
    $savedRoute = SavedRoute::factory()->create([
        'name' => 'Sunday brick',
        'distance_metres' => 25_000,
        'layout' => 'loops',
        'legs' => [
            ['discipline' => 'road', 'distance_metres' => 20_000, 'ascent_metres' => 40, 'coordinates' => [[3.72, 51.05], [3.74, 51.06], [3.72, 51.05]]],
            ['discipline' => 'run', 'distance_metres' => 5_000, 'ascent_metres' => 10, 'coordinates' => [[3.72, 51.05], [3.73, 51.06], [3.72, 51.05]]],
        ],
    ]);

    $html = (string) $this->get(URL::signedRoute('routes.share', $savedRoute))->assertOk()->getContent();

    expect($html)->toContain('<meta property="og:title" content="Sunday brick · 25.0 km">')
        ->and($html)->toContain('Ride 20.0 km, then run 5.0 km. Open it on the map, or send each leg to your watch.');
});

it('shows the Traceway picture until the route has its own', function (): void {
    $savedRoute = SavedRoute::factory()->create();

    $response = $this->get(URL::signedRoute('routes.share.image', ['savedRoute' => $savedRoute->id, 'v' => 0]))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');

    $this->get(route('routes.share.image', ['savedRoute' => $savedRoute->id, 'v' => 0]))->assertForbidden();
});

it('keeps the picture the rider drew and shows it on the shared link', function (): void {
    $user = User::factory()->create();
    $savedRoute = SavedRoute::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('draw_image', expected: true));

    $this->actingAs($user)
        ->post(route('routes.saved.image', $savedRoute), ['image' => routeImage()], ['Accept' => 'application/json'])
        ->assertNoContent();

    Storage::disk('local')->assertExists("route-images/{$user->id}/{$savedRoute->id}.jpg");

    $this->actingAs($user)
        ->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('draw_image', expected: false)
            ->where('route.image_url', fn (string $url): bool => ! str_contains($url, 'v=0')));

    $this->actingAs($user)
        ->get(route('routes.library'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('routes.0.image_drawn', expected: true));

    $image = $this->get(URL::signedRoute('routes.share.image', ['savedRoute' => $savedRoute->id, 'v' => 1]))->assertOk();

    expect($image->headers->get('Content-Type'))->toBe('image/jpeg');
});

it('asks nobody else to draw the picture', function (): void {
    $savedRoute = SavedRoute::factory()->create();

    $this->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('draw_image', expected: false));

    $this->actingAs(User::factory()->create())
        ->get(URL::signedRoute('routes.share', $savedRoute))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('draw_image', expected: false));
});

it('takes a picture only from the rider who saved the route, at the size chats use', function (): void {
    $user = User::factory()->create();
    $savedRoute = SavedRoute::factory()->for($user)->create();

    $this->post(route('routes.saved.image', $savedRoute), ['image' => routeImage()], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->post(route('routes.saved.image', $savedRoute), ['image' => routeImage()], ['Accept' => 'application/json'])
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('routes.saved.image', $savedRoute), ['image' => routeImage(800, 600)], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->post(route('routes.saved.image', $savedRoute), ['image' => UploadedFile::fake()->create('route.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    Storage::disk('local')->assertMissing("route-images/{$user->id}/{$savedRoute->id}.jpg");
});

it('forgets the picture with the route, and every picture with the account', function (): void {
    $user = User::factory()->create();
    [$first, $second] = SavedRoute::factory()->for($user)->count(2)->create();

    foreach ([$first, $second] as $savedRoute) {
        $this->actingAs($user)->post(route('routes.saved.image', $savedRoute), ['image' => routeImage()], ['Accept' => 'application/json'])->assertNoContent();
    }

    $this->actingAs($user)->delete(route('routes.saved.destroy', $first))->assertRedirect();

    Storage::disk('local')->assertMissing("route-images/{$user->id}/{$first->id}.jpg");
    Storage::disk('local')->assertExists("route-images/{$user->id}/{$second->id}.jpg");

    resolve(RouteImageStore::class)->forgetRider(new RiderId($user->id));

    Storage::disk('local')->assertMissing("route-images/{$user->id}");
});
