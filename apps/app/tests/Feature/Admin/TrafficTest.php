<?php

declare(strict_types=1);

use App\Account\Role;
use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSnapshots;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\ScriptedTrafficSource;

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

it('shows the kept days before the live week, for the whole month', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 14:30:00');
    $trafficSnapshots = resolve(TrafficSnapshots::class);
    $trafficSnapshots->save(new TrafficDay('2026-09-19', 50, 50, [['label' => '/too-old', 'count' => 50]], [], []));
    $trafficSnapshots->save(new TrafficDay('2026-09-20', 3, 2, [['label' => '/support', 'count' => 3]], [['label' => 'duckduckgo.com', 'count' => 2]], [['label' => 'France', 'count' => 3]]));
    $trafficSnapshots->save(new TrafficDay('2026-10-12', 4, 3, [['label' => '/', 'count' => 4]], [['label' => '(none)', 'count' => 3]], [['label' => 'Belgium', 'count' => 4]]));
    $trafficSnapshots->save(new TrafficDay('2026-10-13', 99, 99, [['label' => '/live-wins', 'count' => 99]], [], []));

    $source = new ScriptedTrafficSource(trafficReport: new TrafficReport(
        [['date' => '2026-10-18', 'requests' => 1200, 'cached' => 900, 'bytes' => 5_000_000, 'threats' => 4]],
        [['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5], ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6]],
        [['label' => '/', 'count' => 17], ['label' => '/support', 'count' => 1]],
        [['label' => '(none)', 'count' => 11]],
        [['label' => 'Belgium', 'count' => 18]],
    ));
    $this->app->instance(TrafficSource::class, $source);

    $this->actingAs(trafficAdmin())
        ->get(route('admin.traffic'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('traffic.error', expected: null)
                ->where('traffic.visit_days', [
                    ['date' => '2026-09-20', 'page_loads' => 3, 'visits' => 2],
                    ['date' => '2026-10-12', 'page_loads' => 4, 'visits' => 3],
                    ['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5],
                    ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6],
                ])
                ->where('traffic.totals.page_loads', 25)
                ->where('traffic.totals.visits', 16)
                ->where('traffic.totals.requests', 1200)
                ->where('traffic.top_pages', [['label' => '/', 'count' => 21], ['label' => '/support', 'count' => 4]])
                ->where('traffic.top_referrers', [['label' => '(none)', 'count' => 14], ['label' => 'duckduckgo.com', 'count' => 2]])
                ->where('traffic.countries', [['label' => 'Belgium', 'count' => 22], ['label' => 'France', 'count' => 3]])));

    expect($source->asked)->toBe([['since' => '2026-09-20 00:00:00', 'until' => '2026-10-19 14:30:00']]);

    CarbonImmutable::setTestNow();
});
