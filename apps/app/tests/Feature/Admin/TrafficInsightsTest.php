<?php

declare(strict_types=1);

use App\Account\Role;
use App\Domain\Traffic\BackupHealth;
use App\Domain\Traffic\BotTraffic;
use App\Domain\Traffic\RequestBreakdown;
use App\Domain\Traffic\SecurityReport;
use App\Domain\Traffic\SpeedReport;
use App\Domain\Traffic\TrafficInsights;
use App\Domain\Traffic\TrafficUnavailable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function insightsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    return $user;
}

/**
 * Cloudflare as these reports, where a part that is null did not answer. It counts how often it was asked.
 */
function fakeInsights(?BotTraffic $botTraffic = null, ?SpeedReport $speedReport = null, ?BackupHealth $backupHealth = null, ?SecurityReport $securityReport = null): TrafficInsights
{
    return new class($botTraffic, $speedReport, $backupHealth, $securityReport) implements TrafficInsights
    {
        public int $asked = 0;

        public function __construct(
            private readonly ?BotTraffic $botTraffic,
            private readonly ?SpeedReport $speedReport,
            private readonly ?BackupHealth $backupHealth,
            private readonly ?SecurityReport $securityReport,
        ) {}

        public function connected(): bool
        {
            return true;
        }

        public function requests(DateTimeImmutable $now): RequestBreakdown
        {
            return new RequestBreakdown(hours: [['time' => '2026-10-19T11:00:00Z', 'requests' => 42, 'threats' => 2]]);
        }

        public function security(DateTimeImmutable $now): SecurityReport
        {
            return $this->securityReport ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        public function bots(DateTimeImmutable $now): BotTraffic
        {
            $this->asked++;

            return $this->botTraffic ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        public function speed(DateTimeImmutable $now): SpeedReport
        {
            return $this->speedReport ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        public function backups(DateTimeImmutable $now): BackupHealth
        {
            return $this->backupHealth ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }
    };
}

it('says each Traffic page is not connected without a token', function (string $route, string $component): void {
    $this->actingAs(insightsAdmin())
        ->get(route($route))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->missing('insights')
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert->where('insights.connected', expected: false)));
})->with([
    ['admin.traffic.requests', 'admin/traffic/requests'],
    ['admin.traffic.security', 'admin/traffic/security'],
    ['admin.traffic.bots', 'admin/traffic/bots'],
    ['admin.traffic.speed', 'admin/traffic/speed'],
    ['admin.traffic.backups', 'admin/traffic/backups'],
]);

it('keeps the Traffic pages from a rider', function (): void {
    $this->actingAs(User::factory()->create())->get(route('admin.traffic.security'))->assertForbidden();
});

it('shows the bots of the last 30 days, with totals that a row limit cannot lower', function (): void {
    $this->app->instance(TrafficInsights::class, fakeInsights(new BotTraffic(
        verified: [['label' => 'Search Engine Crawler', 'count' => 94], ['label' => 'AI Search', 'count' => 56]],
        reads: [['path' => '/llms.txt', 'method' => 'GET', 'status' => 200, 'count' => 24]],
        readsTotal: 195,
        impostors: [['label' => 'GPTBot', 'count' => 104]],
        impostorsTotal: 2135,
        impostorsBlocked: 1088,
        probes: [['status' => 403, 'count' => 3592], ['status' => 404, 'count' => 483]],
    )));

    $this->actingAs(insightsAdmin())
        ->get(route('admin.traffic.bots'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('insights.error', expected: null)
                ->where('insights.days', BotTraffic::DAYS)
                ->where('insights.verified_total', 150)
                ->where('insights.reads_total', 195)
                ->where('insights.impostors_total', 2135)
                ->where('insights.impostors_blocked', 1088)
                ->where('insights.probes_total', 4075)
                ->where('insights.reads.0.path', '/llms.txt')));
});

it("rates each page's Core Web Vitals, and leaves out what was not measured", function (): void {
    $this->app->instance(TrafficInsights::class, fakeInsights(speedReport: new SpeedReport(vitals: [
        ['path' => '/', 'loads' => 17, 'lcp_ms' => 1564.9, 'inp_ms' => 222.0, 'cls' => null],
    ])));

    $this->actingAs(insightsAdmin())
        ->get(route('admin.traffic.speed'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('insights.vitals.0.lcp', ['value' => 1564.9, 'rating' => 'good', 'label' => 'Good'])
                ->where('insights.vitals.0.inp', ['value' => 222, 'rating' => 'needs_work', 'label' => 'Needs work'])
                ->where('insights.vitals.0.cls', expected: null)));
});

it('shows the firewall blocks with their total, and the backups with their last write', function (): void {
    $this->app->instance(TrafficInsights::class, fakeInsights(
        backupHealth: new BackupHealth('traceway-backups', 4248, 40_255_817, [['date' => '2026-10-17', 'count' => 3010]], '2026-10-17'),
        securityReport: new SecurityReport(blockDays: [['date' => '2026-10-18', 'count' => 770], ['date' => '2026-10-19', 'count' => 470]], peoplePages: [['path' => '/login', 'method' => 'POST', 'rule' => 'custom-rule', 'source' => 'firewallCustom', 'count' => 22]], peopleBlocked: 40),
    ));

    $this->actingAs(insightsAdmin())
        ->get(route('admin.traffic.security'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('insights.blocks', 1240)
                ->where('insights.people_pages.0', ['path' => '/login', 'method' => 'POST', 'rule' => 'custom-rule', 'source' => 'firewallCustom', 'count' => 22])
                ->where('insights.people_blocked', 40)));

    $this->actingAs(insightsAdmin())
        ->get(route('admin.traffic.backups'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('insights.writes', 3010)
                ->where('insights.last_write', '2026-10-17')));
});

it('shows a note on one page when its part fails, and asks again after five minutes', function (): void {
    $this->travelTo('2026-10-19 10:00:00');
    $trafficInsights = fakeInsights();
    $this->app->instance(TrafficInsights::class, $trafficInsights);

    foreach ([0, 4, 6] as $minutes) {
        $this->travelTo(now()->setTime(10, $minutes));
        $this->actingAs(insightsAdmin())
            ->get(route('admin.traffic.bots'))
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                    ->where('insights.connected', expected: true)
                    ->where('insights.error', 'Cloudflare did not answer.')
                    ->where('insights.verified', [])));
    }

    expect($trafficInsights->asked)->toBe(2);

    $this->actingAs(insightsAdmin())
        ->get(route('admin.traffic.requests'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('insights.error', expected: null)
                ->where('insights.hours.0.requests', 42)));
});
