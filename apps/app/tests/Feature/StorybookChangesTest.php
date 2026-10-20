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
