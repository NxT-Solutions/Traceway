<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads a fit course for a signed in rider', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('routes.fit'), [
            'name' => 'Morning loop',
            'sport' => 'road',
            'coordinates' => [
                [3.72, 51.05],
                [3.8, 51.08],
                [3.74, 51.06],
            ],
        ]);

    $response->assertOk();
    $response->assertHeader('content-type', 'application/octet-stream');

    $body = $response->getContent();

    expect($body)->toBeString()
        ->and(substr($body, 8, 4))->toBe('.FIT');
});

it('asks a guest to log in before downloading a course', function (): void {
    $this->postJson(route('routes.fit'), [])
        ->assertUnauthorized();
});
