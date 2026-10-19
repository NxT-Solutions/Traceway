<?php

declare(strict_types=1);

use App\Account\Role;
use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSnapshots;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use App\Models\User;
use App\Queries\Admin\TrafficQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\ScriptedTrafficSource;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

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

        public function readsVisits(): bool
        {
            return true;
        }

        public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
        {
            throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        public function visits(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
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
});

it('still shows the kept days when Cloudflare does not answer', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 14:30:00');
    resolve(TrafficSnapshots::class)->save(new TrafficDay('2026-10-01', 6, 4, [['label' => '/', 'count' => 6]], [['label' => '(none)', 'count' => 4]], [['label' => 'Belgium', 'count' => 6]]));
    $this->app->instance(TrafficSource::class, new ScriptedTrafficSource(['2026-09-20' => null]));

    $this->actingAs(trafficAdmin())
        ->get(route('admin.traffic'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $assert): Assert => $assert
                ->where('traffic.error', 'Cloudflare did not answer.')
                ->where('traffic.visit_days', [['date' => '2026-10-01', 'page_loads' => 6, 'visits' => 4]])
                ->where('traffic.totals.visits', 4)
                ->where('traffic.top_pages', [['label' => '/', 'count' => 6]])));
});

function wholeWeek(): TrafficReport
{
    return new TrafficReport(
        [['date' => '2026-10-18', 'requests' => 1200, 'cached' => 900, 'bytes' => 5_000_000, 'threats' => 4]],
        [['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5], ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6]],
        [['label' => '/', 'count' => 18]],
        [['label' => '(none)', 'count' => 11]],
        [['label' => 'Belgium', 'count' => 18]],
    );
}

dataset('partial answers', [
    'no days' => fn (): TrafficReport => new TrafficReport([], [], wholeWeek()->topPages, wholeWeek()->topReferrers, wholeWeek()->countries),
    'no pages' => fn (): TrafficReport => new TrafficReport([], wholeWeek()->visitDays, [], wholeWeek()->topReferrers, wholeWeek()->countries),
    'no referrers' => fn (): TrafficReport => new TrafficReport([], wholeWeek()->visitDays, wholeWeek()->topPages, [], wholeWeek()->countries),
    'no countries' => fn (): TrafficReport => new TrafficReport([], wholeWeek()->visitDays, wholeWeek()->topPages, wholeWeek()->topReferrers, []),
    'coarse, with no visits' => fn (): TrafficReport => new TrafficReport([], [['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 0]], wholeWeek()->topPages, wholeWeek()->topReferrers, wholeWeek()->countries),
]);

it('asks once more when the live answer is partial, and keeps the whole one for an hour', function (TrafficReport $trafficReport): void {
    CarbonImmutable::setTestNow('2026-10-19 14:05:00');
    $source = new ScriptedTrafficSource(answers: [$trafficReport, wholeWeek()]);
    $this->app->instance(TrafficSource::class, $source);
    $trafficQuery = resolve(TrafficQuery::class);

    $page = $trafficQuery->page();

    expect($page['totals']['visits'])->toBe(11)
        ->and($page['countries'])->toBe([['label' => 'Belgium', 'count' => 18]])
        ->and($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:55:00');

    expect($trafficQuery->page()['totals']['visits'])->toBe(11)
        ->and($source->asked)->toHaveCount(2);
})->with('partial answers');

it('keeps a live answer that is still partial for five minutes only', function (TrafficReport $trafficReport): void {
    CarbonImmutable::setTestNow('2026-10-19 14:05:00');
    $source = new ScriptedTrafficSource(answers: [$trafficReport, $trafficReport, wholeWeek()]);
    $this->app->instance(TrafficSource::class, $source);
    $trafficQuery = resolve(TrafficQuery::class);

    expect($trafficQuery->page()['error'])->toBeNull()
        ->and($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:09:00');
    $trafficQuery->page();

    expect($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:11:00');

    expect($trafficQuery->page()['totals']['visits'])->toBe(11)
        ->and($source->asked)->toHaveCount(3);
})->with('partial answers');

it('shows the partial answer when Cloudflare does not answer the second time', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 14:05:00');
    $partial = new TrafficReport(wholeWeek()->requestDays, [], wholeWeek()->topPages, wholeWeek()->topReferrers, wholeWeek()->countries);
    $source = new ScriptedTrafficSource(answers: [$partial, null, wholeWeek()]);
    $this->app->instance(TrafficSource::class, $source);
    $trafficQuery = resolve(TrafficQuery::class);

    $page = $trafficQuery->page();

    expect($page['error'])->toBeNull()
        ->and($page['totals']['requests'])->toBe(1200)
        ->and($page['top_pages'])->toBe([['label' => '/', 'count' => 18]])
        ->and($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:11:00');

    expect($trafficQuery->page()['totals']['visits'])->toBe(11)
        ->and($source->asked)->toHaveCount(3);
});

it('asks once more for a week with no page loads at all, and keeps it for five minutes only', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 14:05:00');
    $source = new ScriptedTrafficSource(trafficReport: new TrafficReport(wholeWeek()->requestDays, [], [], [], []));
    $this->app->instance(TrafficSource::class, $source);
    $trafficQuery = resolve(TrafficQuery::class);

    expect($trafficQuery->page()['totals']['visits'])->toBe(0)
        ->and($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:09:00');
    $trafficQuery->page();

    expect($source->asked)->toHaveCount(2);

    CarbonImmutable::setTestNow('2026-10-19 14:11:00');
    $trafficQuery->page();

    expect($source->asked)->toHaveCount(4);
});

it('keeps the requests for an hour when Traceway has no ids for visits', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 14:05:00');
    $source = new ScriptedTrafficSource(trafficReport: new TrafficReport(wholeWeek()->requestDays, [], [], [], []), readsVisits: false);
    $this->app->instance(TrafficSource::class, $source);
    $trafficQuery = resolve(TrafficQuery::class);

    expect($trafficQuery->page()['totals']['requests'])->toBe(1200)
        ->and($source->asked)->toHaveCount(1);

    CarbonImmutable::setTestNow('2026-10-19 14:55:00');
    $trafficQuery->page();

    expect($source->asked)->toHaveCount(1);
});
