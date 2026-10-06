<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hides demo login outside the local environment', function () {
    $this->post(route('login.demo', 'road'))->assertNotFound();
});

it('signs in a seeded local rider', function () {
    config(['demo.enabled' => true]);

    User::factory()->create([
        'name' => 'Road rider',
        'email' => 'road@traceway.test',
    ]);

    $this->post(route('login.demo', 'road'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});
