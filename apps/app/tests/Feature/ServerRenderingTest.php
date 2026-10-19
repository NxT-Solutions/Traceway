<?php

declare(strict_types=1);

use App\Account\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * In production bun runs bootstrap/ssr/app.js next to PHP, and Laravel asks it for each page's HTML before the
 * browser takes over. Here a fake stands in for it.
 */
beforeEach(function (): void {
    config(['inertia.ssr.ensure_bundle_exists' => false]);

    Http::fake([
        '127.0.0.1:13714/*' => Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app">Drawn on the server</div>',
        ]),
    ]);
});

it('draws the sign-in pages and the status page on the server', function (string $path): void {
    $this->get($path)
        ->assertOk()
        ->assertSee('Drawn on the server');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:13714/render');
})->with(['/login', '/register', '/forgot-password', '/status']);

it('leaves the planner, the library, the settings, and the admin pages to the browser', function (string $path): void {
    $rider = User::factory()->create();
    $rider->assignRole(Role::SuperAdmin->value);

    $this->actingAs($rider)
        ->get($path)
        ->assertOk()
        ->assertDontSee('Drawn on the server');

    Http::assertNothingSent();
})->with(['/dashboard', '/saved', '/settings/profile', '/settings/devices', '/admin', '/admin/users']);

it('draws the page in the browser when the server renderer is down', function (): void {
    Http::fake(['127.0.0.1:13714/*' => Http::failedConnection()]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('<div id="app"></div>', escape: false);
});

it('describes every planner page for search', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<meta name="description" content="Plan a loop for any sport and send it to your watch, GPS, or bike computer.">', escape: false);
});
