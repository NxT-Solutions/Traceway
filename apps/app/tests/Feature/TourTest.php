<?php

declare(strict_types=1);

use App\Auth\Impersonation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows a new rider around the planner', function (): void {
    $this->actingAs(User::factory()->untoured()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('dashboard')->where('showTour', expected: true));
});

it('does not show the tour again once the rider finished or closed it', function (): void {
    $user = User::factory()->untoured()->create();

    $this->actingAs($user)->post(route('tour.finish'))->assertNoContent();

    expect($user->refresh()->toured_at)->not->toBeNull();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('showTour', expected: false));
});

it('keeps the first time the rider was shown around', function (): void {
    $user = User::factory()->create(['toured_at' => now()->subWeek()]);
    $touredAt = $user->toured_at?->toIso8601String();

    $this->actingAs($user)->post(route('tour.finish'))->assertNoContent();

    expect($user->refresh()->toured_at?->toIso8601String())->toBe($touredAt);
});

it('does not use up the tour of a rider an admin acts as', function (): void {
    $admin = User::factory()->create();
    $rider = User::factory()->untoured()->create();

    $this->actingAs($rider)
        ->withSession([Impersonation::SESSION_KEY => $admin->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('showTour', expected: false));
});

it('asks a guest to sign in before the tour can be finished', function (): void {
    $this->post(route('tour.finish'))->assertRedirect(route('login'));
});
