<?php

declare(strict_types=1);

use App\Domain\Traffic\EmptyDay;
use Carbon\CarbonImmutable;

test('a day is settled after three empty answers six hours apart', function (): void {
    $emptyDay = EmptyDay::first('2026-10-18', CarbonImmutable::parse('2026-10-19 03:00:00 UTC'));

    expect($emptyDay->settled())->toBeFalse();

    $emptyDay = $emptyDay->answeredAgain(CarbonImmutable::parse('2026-10-19 09:00:00 UTC'));

    expect($emptyDay->answers)->toBe(2)
        ->and($emptyDay->settled())->toBeFalse();

    $emptyDay = $emptyDay->answeredAgain(CarbonImmutable::parse('2026-10-19 15:00:00 UTC'));

    expect($emptyDay->answers)->toBe(3)
        ->and($emptyDay->settled())->toBeTrue()
        ->and($emptyDay->trafficDay()->visitDay())->toBe(['date' => '2026-10-18', 'page_loads' => 0, 'visits' => 0])
        ->and($emptyDay->trafficDay()->topPages)->toBe([]);
});

test('empty answers sooner than six hours after the last one do not count', function (): void {
    $emptyDay = EmptyDay::first('2026-10-18', CarbonImmutable::parse('2026-10-19 03:00:00 UTC'));

    foreach (['04:00', '05:00', '06:00', '07:00', '08:00'] as $hour) {
        $emptyDay = $emptyDay->answeredAgain(CarbonImmutable::parse('2026-10-19 '.$hour.' UTC'));
    }

    expect($emptyDay->answers)->toBe(1)
        ->and($emptyDay->lastCountedAt->format('H:i'))->toBe('03:00');
});
