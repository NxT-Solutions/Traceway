<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

it('answers the public pages', function (): void {
    config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);

    $this->get('/')->assertOk();
    $this->get('/up')->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
    $this->get(route('login.options'))->assertOk();
    $this->get(route('password.request'))->assertOk();
    $this->get(route('well-known.passkeys'))->assertOk();
    $this->get('/share/00000000-0000-0000-0000-000000000000')->assertForbidden();
    $this->get('/share/00000000-0000-0000-0000-000000000000/gpx')->assertForbidden();
    $this->get('/share/00000000-0000-0000-0000-000000000000/fit')->assertForbidden();
})->group('smoke');

it('sends a guest away from the planner and the rider pages', function (): void {
    $this->get(route('dashboard'))->assertRedirect();
    $this->get(route('routes.library'))->assertRedirect();
    $this->get(route('profile.edit'))->assertRedirect();
    $this->get(route('privacy.edit'))->assertRedirect();
    $this->get(route('privacy.export'))->assertRedirect();
    $this->get(route('appearance.edit'))->assertRedirect();
    $this->get(route('security.edit'))->assertRedirect();
    $this->get(route('connections.edit'))->assertRedirect();
    $this->postJson(route('routes.saved.store'), [])->assertUnauthorized();
    $this->postJson(route('routes.preview'), [])->assertUnauthorized();
    $this->postJson(route('routes.build'), [])->assertUnauthorized();
    $this->postJson(route('routes.snap'), [])->assertUnauthorized();
    $this->postJson(route('routes.fit'), [])->assertUnauthorized();
    $this->deleteJson(route('routes.saved.destroy', '00000000-0000-0000-0000-000000000000'))->assertUnauthorized();
})->group('smoke');

it('opens the rider pages after login', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('routes.library'))->assertOk();
    $this->actingAs($user)->get('/settings')->assertRedirect('/settings/profile');
    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    $this->actingAs($user)->get(route('privacy.edit'))->assertOk();
    $this->actingAs($user)->get(route('appearance.edit'))->assertOk();
    $this->actingAs($user)->get(route('connections.edit'))->assertOk();
    $this->actingAs($user)->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
        ->get(route('security.edit'))
        ->assertOk();
})->group('smoke');
