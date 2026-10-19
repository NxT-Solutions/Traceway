<?php

declare(strict_types=1);

use App\Account\Role;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function trafficAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    return $user;
}

it('says the traffic is not connected without a token', function (): void {
    $this->actingAs(trafficAdmin())
        ->get(route('admin.traffic'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('admin/traffic')
            ->missing('traffic')
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert->where('traffic.connected', expected: false)));
});

it('shows a message when Cloudflare does not answer', function (): void {
    $this->app->bind(TrafficSource::class, fn (): TrafficSource => new class implements TrafficSource
    {
        public function connected(): bool
        {
            return true;
        }

        public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
        {
            throw new TrafficUnavailable('Cloudflare did not answer.');
        }
    });

    $this->actingAs(trafficAdmin())
        ->get(route('admin.traffic'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('traffic.connected', expected: true)
                ->where('traffic.error', 'Cloudflare did not answer.')));
});
