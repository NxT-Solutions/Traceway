<?php

declare(strict_types=1);

use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\VisitWindow;
use Carbon\CarbonImmutable;

test('the visit window starts at midnight UTC six days before the day asked', function (string $until, string $start): void {
    expect(VisitWindow::start(CarbonImmutable::parse($until))->format('Y-m-d\TH:i:sP'))->toBe($start);
})->with([
    'afternoon' => ['2026-10-19 14:30:00', '2026-10-13T00:00:00+00:00'],
    'just after midnight' => ['2026-10-19 00:00:01', '2026-10-13T00:00:00+00:00'],
    'Brussels morning is the UTC day before' => ['2026-10-19 01:30:00 Europe/Brussels', '2026-10-12T00:00:00+00:00'],
]);

test('a day is read out of a report of that day', function (): void {
    $trafficDay = TrafficDay::fromReport(CarbonImmutable::parse('2026-10-15 00:00:00 Europe/Brussels')->addHours(2), new TrafficReport(
        [],
        [['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5]],
        [['label' => '/', 'count' => 10]],
        [['label' => '(none)', 'count' => 5]],
        [['label' => 'BE', 'count' => 10]],
    ));

    expect($trafficDay->date)->toBe('2026-10-15')
        ->and($trafficDay->visitDay())->toBe(['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5])
        ->and($trafficDay->topPages)->toBe([['label' => '/', 'count' => 10]])
        ->and($trafficDay->complete())->toBeTrue();
});

test('a day with no page loads or an empty list is not complete', function (array $visitDays, array $pages, array $referrers, array $countries): void {
    $trafficDay = TrafficDay::fromReport(CarbonImmutable::parse('2026-10-15'), new TrafficReport([], $visitDays, $pages, $referrers, $countries));

    expect($trafficDay->complete())->toBeFalse();
})->with([
    'no day' => [[], [['label' => '/', 'count' => 1]], [['label' => '(none)', 'count' => 1]], [['label' => 'BE', 'count' => 1]]],
    'another day' => [[['date' => '2026-10-14', 'page_loads' => 3, 'visits' => 1]], [['label' => '/', 'count' => 1]], [['label' => '(none)', 'count' => 1]], [['label' => 'BE', 'count' => 1]]],
    'no pages' => [[['date' => '2026-10-15', 'page_loads' => 3, 'visits' => 1]], [], [['label' => '(none)', 'count' => 1]], [['label' => 'BE', 'count' => 1]]],
    'no referrers' => [[['date' => '2026-10-15', 'page_loads' => 3, 'visits' => 1]], [['label' => '/', 'count' => 1]], [], [['label' => 'BE', 'count' => 1]]],
    'no countries' => [[['date' => '2026-10-15', 'page_loads' => 3, 'visits' => 1]], [['label' => '/', 'count' => 1]], [['label' => '(none)', 'count' => 1]], []],
]);

test('kept days are added before the live week, and their lists are summed', function (): void {
    $trafficReport = new TrafficReport(
        [['date' => '2026-10-18', 'requests' => 9, 'cached' => 1, 'bytes' => 2, 'threats' => 0]],
        [['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5], ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6]],
        [['label' => '/', 'count' => 17], ['label' => '/support', 'count' => 1]],
        [['label' => '(none)', 'count' => 11]],
        [['label' => 'BE', 'count' => 12], ['label' => 'IT', 'count' => 6]],
    );

    $merged = $trafficReport->withDays([
        new TrafficDay('2026-10-01', 4, 2, [['label' => '/support', 'count' => 3], ['label' => '/', 'count' => 1]], [['label' => 'duckduckgo.com', 'count' => 2]], [['label' => 'IT', 'count' => 4]]),
        new TrafficDay('2026-10-15', 99, 99, [['label' => '/old', 'count' => 99]], [], []),
    ]);

    expect($merged->requestDays)->toBe($trafficReport->requestDays)
        ->and($merged->visitDays)->toBe([
            ['date' => '2026-10-01', 'page_loads' => 4, 'visits' => 2],
            ['date' => '2026-10-15', 'page_loads' => 10, 'visits' => 5],
            ['date' => '2026-10-17', 'page_loads' => 8, 'visits' => 6],
        ])
        ->and($merged->topPages)->toBe([['label' => '/', 'count' => 18], ['label' => '/support', 'count' => 4]])
        ->and($merged->topReferrers)->toBe([['label' => '(none)', 'count' => 11], ['label' => 'duckduckgo.com', 'count' => 2]])
        ->and($merged->countries)->toBe([['label' => 'BE', 'count' => 12], ['label' => 'IT', 'count' => 10]]);
});

test('the summed lists keep the ten biggest', function (): void {
    $pages = array_map(fn (int $index): array => ['label' => '/'.$index, 'count' => $index], range(1, 12));

    $trafficReport = new TrafficReport([], [], $pages, [], [])
        ->withDays([new TrafficDay('2026-10-01', 1, 1, [['label' => '/1', 'count' => 20]], [], [])]);

    expect($trafficReport->topPages)->toHaveCount(10)
        ->and($trafficReport->topPages[0])->toBe(['label' => '/1', 'count' => 21])
        ->and($trafficReport->topPages[9])->toBe(['label' => '/4', 'count' => 4]);
});
