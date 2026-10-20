<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Storybook runs at /storybook on the same site, with a signed-in rider's cookies. A request a story's mocks miss
 * reaches Laravel for real, so a change from a Storybook page is refused.
 */
it('refuses a change sent from a Storybook page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout', [], ['Referer' => url('/storybook/app/iframe.html?id=pages-settings-profile--saves-name')])
        ->assertForbidden();

    $this->assertAuthenticatedAs($user);
});

it('lets the planner itself change things', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout', [], ['Referer' => url('/settings/profile')])
        ->assertRedirect();

    $this->assertGuest();
});

it('still lets a Storybook page read a page', function (): void {
    $this->get('/login', ['Referer' => url('/storybook/app/iframe.html')])
        ->assertOk();
});

it('goes by the path of this site, not of another one', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout', [], ['Referer' => 'https://example.com/storybook/app/iframe.html'])
        ->assertRedirect();

    $this->assertGuest();
});

it('refuses every kind of change, and answers a JSON request with a 403 too', function (string $method, string $path): void {
    $user = User::factory()->create(['name' => 'Sam Rivera']);

    $this->actingAs($user)
        ->json($method, $path, ['name' => 'Changed in a story'], ['Referer' => url('/storybook/app/iframe.html')])
        ->assertForbidden();

    expect($user->refresh())->name->toBe('Sam Rivera');
    $this->assertAuthenticatedAs($user);
})->with([
    'saving the profile' => ['PATCH', '/settings/profile'],
    'changing the password' => ['PUT', '/settings/password'],
    'deleting the account' => ['DELETE', '/settings/profile'],
    'logging out' => ['POST', '/logout'],
]);

it('leaves a change with no Referer to the CSRF check and to Storybook itself', function (): void {
    // A browser or an extension can drop the Referer. Storybook then still answers or refuses the request itself,
    // and hides the XSRF-TOKEN cookie, so a request that slips past both fails the CSRF check (off in tests).
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect();

    $this->assertGuest();
});

it('lets the Storybook overview, a page of the site, send changes like any other page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout', [], ['Referer' => url('/storybook')])
        ->assertRedirect();

    $this->assertGuest();
});
