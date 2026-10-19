<?php

declare(strict_types=1);

use App\Domain\Traffic\EmptyDay;
use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSnapshots;
use App\Domain\Traffic\TrafficSource;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\ScriptedTrafficSource;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function trafficDayReport(string $date, int $pageLoads, int $visits): TrafficReport
{
    return new TrafficReport(
        [['date' => $date, 'requests' => 100, 'cached' => 50, 'bytes' => 1000, 'threats' => 0]],
        [['date' => $date, 'page_loads' => $pageLoads, 'visits' => $visits]],
        [['label' => '/', 'count' => $pageLoads]],
        [['label' => '(none)', 'count' => $visits]],
        [['label' => 'Belgium', 'count' => $pageLoads]],
    );
}

it('keeps each finished day of the visit window that Cloudflare answers in full', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 03:20:00');
    $source = new ScriptedTrafficSource([
        '2026-10-13' => trafficDayReport('2026-10-13', 4, 2),
        '2026-10-14' => null,
        '2026-10-15' => trafficDayReport('2026-10-15', 10, 5),
        '2026-10-16' => new TrafficReport([], [['date' => '2026-10-16', 'page_loads' => 3, 'visits' => 1]], [], [], []),
        '2026-10-17' => trafficDayReport('2026-10-17', 8, 6),
    ]);
    $this->app->instance(TrafficSource::class, $source);

    Artisan::call('traffic:snapshot');

    expect(Artisan::output())->toContain('3 more days')
        ->and(array_column($source->asked, 'since'))->toBe([
            '2026-10-13 00:00:00', '2026-10-14 00:00:00', '2026-10-15 00:00:00',
            '2026-10-16 00:00:00', '2026-10-17 00:00:00', '2026-10-18 00:00:00',
        ])
        ->and($source->asked[5]['until'])->toBe('2026-10-18 23:59:59')
        ->and(DB::table('traffic_days')->orderBy('day')->pluck('visits', 'day')->all())->toBe([
            '2026-10-13' => 2, '2026-10-15' => 5, '2026-10-17' => 6,
        ]);

    $kept = resolve(TrafficSnapshots::class)->between(CarbonImmutable::parse('2026-10-15'), CarbonImmutable::parse('2026-10-15'));

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->visitDay())->toBe(['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5])
        ->and($kept[0]->topPages)->toBe([['label' => '/', 'count' => 10]])
        ->and($kept[0]->topReferrers)->toBe([['label' => '(none)', 'count' => 5]])
        ->and($kept[0]->countries)->toBe([['label' => 'Belgium', 'count' => 10]]);
});

it('waits three hours after a day ends before it keeps it', function (string $now, string $first, string $last): void {
    CarbonImmutable::setTestNow($now);
    $source = new ScriptedTrafficSource;
    $this->app->instance(TrafficSource::class, $source);

    Artisan::call('traffic:snapshot');

    $asked = array_column($source->asked, 'since');

    expect($asked[0])->toBe($first)
        ->and($asked[count($asked) - 1])->toBe($last);
})->with([
    'just after midnight' => ['2026-10-19 00:20:00', '2026-10-13 00:00:00', '2026-10-17 00:00:00'],
    'three hours after midnight' => ['2026-10-19 03:20:00', '2026-10-13 00:00:00', '2026-10-18 00:00:00'],
    'late in the day' => ['2026-10-19 23:20:00', '2026-10-13 00:00:00', '2026-10-18 00:00:00'],
]);

it('asks again only for the days not kept yet, and forgets days older than a season', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 13:20:00');
    $trafficSnapshots = resolve(TrafficSnapshots::class);

    foreach (['2026-07-20', '2026-07-21', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'] as $date) {
        $trafficSnapshots->save(TrafficDay::fromReport(CarbonImmutable::parse($date), trafficDayReport($date, 1, 1)));
    }

    $source = new ScriptedTrafficSource(['2026-10-18' => trafficDayReport('2026-10-18', 2, 2)]);
    $this->app->instance(TrafficSource::class, $source);

    Artisan::call('traffic:snapshot');

    expect(array_column($source->asked, 'since'))->toBe(['2026-10-18 00:00:00'])
        ->and(DB::table('traffic_days')->orderBy('day')->pluck('day')->all())->toBe([
            '2026-07-21', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18',
        ]);
});

it('asks nothing without the Analytics keys', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 13:20:00');
    $source = new ScriptedTrafficSource(connected: false);
    $this->app->instance(TrafficSource::class, $source);

    Artisan::call('traffic:snapshot');

    expect($source->asked)->toBe([])
        ->and(DB::table('traffic_days')->count())->toBe(0);
});

it('asks nothing, and keeps no empty days, when Traceway has the zone but no ids for visits', function (): void {
    foreach (['2026-10-19 03:20:00', '2026-10-19 09:20:00', '2026-10-19 15:20:00'] as $now) {
        CarbonImmutable::setTestNow($now);
        $source = new ScriptedTrafficSource(readsVisits: false);
        $this->app->instance(TrafficSource::class, $source);

        Artisan::call('traffic:snapshot');

        expect($source->asked)->toBe([]);
    }

    expect(DB::table('traffic_days')->count())->toBe(0)
        ->and(DB::table('traffic_empty_days')->count())->toBe(0);
});

it('replaces a kept day that is saved again', function (): void {
    $trafficSnapshots = resolve(TrafficSnapshots::class);
    $trafficSnapshots->save(new TrafficDay('2026-10-15', 1, 1, [], [], []));
    $trafficSnapshots->save(new TrafficDay('2026-10-15', 10, 5, [['label' => '/', 'count' => 10]], [], []));

    $kept = $trafficSnapshots->between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->visitDay())->toBe(['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5])
        ->and($kept[0]->topPages)->toBe([['label' => '/', 'count' => 10]]);
});

/**
 * Runs the snapshot at this moment, with a source that answers the days from the 13th to the 17th in full and the
 * 18th with this answer, and keeps the days it answers in full.
 */
function snapshotAt(string $now, TrafficReport $trafficReport): ScriptedTrafficSource
{
    CarbonImmutable::setTestNow($now);
    $days = ['2026-10-18' => $trafficReport];

    foreach (['2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'] as $date) {
        $days[$date] = trafficDayReport($date, 4, 2);
    }

    $source = new ScriptedTrafficSource($days);
    app()->instance(TrafficSource::class, $source);
    Artisan::call('traffic:snapshot');

    return $source;
}

it('asks Cloudflare for the visits only, not the requests', function (): void {
    $scriptedTrafficSource = snapshotAt('2026-10-19 03:20:00', trafficDayReport('2026-10-18', 2, 2));

    expect($scriptedTrafficSource->methods)->toBe(array_fill(0, 6, 'visits'))
        ->and(DB::table('traffic_days')->count())->toBe(6);
});

it('keeps a day with no page loads once it came back empty three times, six hours apart, and asks it no more', function (): void {
    $empty = new TrafficReport([], [], [], [], []);

    snapshotAt('2026-10-19 03:20:00', $empty);
    snapshotAt('2026-10-19 04:20:00', $empty);
    snapshotAt('2026-10-19 08:20:00', $empty);

    expect(DB::table('traffic_empty_days')->pluck('answers', 'day')->all())->toBe(['2026-10-18' => 1]);

    snapshotAt('2026-10-19 09:20:00', $empty);

    expect(DB::table('traffic_empty_days')->pluck('answers', 'day')->all())->toBe(['2026-10-18' => 2])
        ->and(DB::table('traffic_days')->where('day', '2026-10-18')->exists())->toBeFalse();

    snapshotAt('2026-10-19 15:20:00', $empty);

    expect(Artisan::output())->toContain('1 more days')
        ->and(DB::table('traffic_empty_days')->count())->toBe(0)
        ->and(DB::table('traffic_days')->where('day', '2026-10-18')->first(['page_loads', 'visits']))->toEqual((object) ['page_loads' => 0, 'visits' => 0]);

    $scriptedTrafficSource = snapshotAt('2026-10-19 16:20:00', $empty);

    expect($scriptedTrafficSource->asked)->toBe([]);
});

it('starts the count over when a day that came back empty shows a page after all', function (): void {
    $empty = new TrafficReport([], [], [], [], []);

    snapshotAt('2026-10-19 03:20:00', $empty);
    snapshotAt('2026-10-19 09:20:00', $empty);
    snapshotAt('2026-10-19 15:20:00', new TrafficReport([], [], [['label' => '/', 'count' => 3]], [], []));

    expect(DB::table('traffic_empty_days')->count())->toBe(0);

    snapshotAt('2026-10-19 16:20:00', $empty);
    snapshotAt('2026-10-19 22:20:00', $empty);

    expect(DB::table('traffic_empty_days')->pluck('answers', 'day')->all())->toBe(['2026-10-18' => 2])
        ->and(DB::table('traffic_days')->where('day', '2026-10-18')->exists())->toBeFalse();
});

it('never keeps a coarse answer with page loads and no visits, however often it comes', function (): void {
    $coarse = new TrafficReport([], [['date' => '2026-10-18', 'page_loads' => 10, 'visits' => 0]], [['label' => '/', 'count' => 10]], [['label' => '(none)', 'count' => 10]], [['label' => 'Belgium', 'count' => 10]]);

    foreach (['2026-10-19 03:20:00', '2026-10-19 09:20:00', '2026-10-19 15:20:00', '2026-10-19 21:20:00'] as $now) {
        $source = snapshotAt($now, $coarse);
    }

    expect($source->asked)->toBe([['since' => '2026-10-18 00:00:00', 'until' => '2026-10-18 23:59:59']])
        ->and(DB::table('traffic_days')->where('day', '2026-10-18')->exists())->toBeFalse()
        ->and(DB::table('traffic_empty_days')->count())->toBe(0);
});

it('keeps counting a day that came back empty when Cloudflare does not answer in between', function (): void {
    $empty = new TrafficReport([], [], [], [], []);

    snapshotAt('2026-10-19 03:20:00', $empty);
    CarbonImmutable::setTestNow('2026-10-19 09:20:00');
    $this->app->instance(TrafficSource::class, new ScriptedTrafficSource(['2026-10-18' => null]));
    Artisan::call('traffic:snapshot');

    expect(DB::table('traffic_empty_days')->pluck('answers', 'day')->all())->toBe(['2026-10-18' => 1]);
});

it('forgets the counts of empty answers older than a season', function (): void {
    CarbonImmutable::setTestNow('2026-10-19 13:20:00');
    $trafficSnapshots = resolve(TrafficSnapshots::class);
    $trafficSnapshots->saveEmpty(EmptyDay::first('2026-07-20', CarbonImmutable::parse('2026-07-21 03:00:00')));
    $trafficSnapshots->saveEmpty(EmptyDay::first('2026-07-21', CarbonImmutable::parse('2026-07-22 03:00:00')));

    $trafficSnapshots->forgetBefore(CarbonImmutable::parse('2026-07-21'));

    expect(DB::table('traffic_empty_days')->pluck('day')->all())->toBe(['2026-07-21'])
        ->and($trafficSnapshots->emptyDays(CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-07-31'))[0]->lastCountedAt->format('Y-m-d H:i:s'))->toBe('2026-07-22 03:00:00');
});

it('runs every hour on the production scheduler', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'traffic:snapshot'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('20 * * * *');
});
