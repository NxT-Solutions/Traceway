<?php

declare(strict_types=1);

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

it('replaces a kept day that is saved again', function (): void {
    $trafficSnapshots = resolve(TrafficSnapshots::class);
    $trafficSnapshots->save(new TrafficDay('2026-10-15', 1, 1, [], [], []));
    $trafficSnapshots->save(new TrafficDay('2026-10-15', 10, 5, [['label' => '/', 'count' => 10]], [], []));

    $kept = $trafficSnapshots->between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->visitDay())->toBe(['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5])
        ->and($kept[0]->topPages)->toBe([['label' => '/', 'count' => 10]]);
});

it('runs every hour on the production scheduler', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'traffic:snapshot'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('20 * * * *');
});
