<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DemoRiderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('hides demo login outside the local environment', function (): void {
    $this->post(route('login.demo', 'road'))->assertNotFound();
});

it('signs in a seeded local rider', function (): void {
    config(['demo.enabled' => true]);

    User::factory()->create([
        'name' => 'Road rider',
        'email' => 'road@traceway.test',
    ]);

    $this->post(route('login.demo', 'road'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

it('gives each demo rider a role and shows it on the sign-in page', function (): void {
    config(['demo.enabled' => true]);

    $this->seed(DemoRiderSeeder::class);

    expect(User::query()->where('email', 'road@traceway.test')->firstOrFail()->getRoleNames()->all())->toBe(['super-admin'])
        ->and(User::query()->where('email', 'gravel@traceway.test')->firstOrFail()->getRoleNames()->all())->toBe(['admin'])
        ->and(User::query()->where('email', 'run@traceway.test')->firstOrFail()->getRoleNames()->all())->toBe(['user']);

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('demoRiders.0.role', 'Super Admin')
            ->where('demoRiders.1.role', 'Admin')
            ->where('demoRiders.2.role', 'User'));
});
