<?php

declare(strict_types=1);

use App\Domain\Status\Availability;
use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use App\Domain\Status\StatusRange;

test('each bar shows the worst state it had, and a bar with no checks shows none', function (): void {
    $bars = Availability::bars([
        '2026-10-14 00:00:00' => ['up' => 280, 'slow' => 8],
        '2026-10-16 00:00:00' => ['up' => 100, 'down' => 2, 'waiting' => 1],
    ], new DateTimeImmutable('2026-10-14 00:00:00', new DateTimeZone('UTC')), 3, 24);

    expect(array_map(fn (array $bar): array => [$bar['start']->format('Y-m-d'), $bar['state']], $bars))->toBe([
        ['2026-10-14', ServiceState::Slow],
        ['2026-10-15', null],
        ['2026-10-16', ServiceState::Down],
    ]);
});

test('days fold into the week they fall in, and days before the first bar are left out', function (): void {
    $bars = Availability::bars([
        '2026-10-01 00:00:00' => ['down' => 3],
        '2026-10-09 00:00:00' => ['slow' => 1],
        '2026-10-15 00:00:00' => ['up' => 288],
        '2026-10-16 00:00:00' => ['down' => 1],
    ], new DateTimeImmutable('2026-10-03 00:00:00', new DateTimeZone('UTC')), 2, 168);

    expect(array_map(fn (array $bar): array => [$bar['start']->format('Y-m-d'), $bar['state']], $bars))->toBe([
        ['2026-10-03', ServiceState::Slow],
        ['2026-10-10', ServiceState::Down],
    ]);
});

test('a range ends with now: the hour so far, the day so far, or the week that ends today', function (): void {
    $now = new DateTimeImmutable('2026-10-29 10:20:00', new DateTimeZone('UTC'));

    expect(StatusRange::Day->firstBar($now)->format('Y-m-d H:i'))->toBe('2026-10-28 11:00')
        ->and(StatusRange::Week->firstBar($now)->format('Y-m-d H:i'))->toBe('2026-10-23 00:00')
        ->and(StatusRange::Quarter->firstBar($now)->format('Y-m-d H:i'))->toBe('2026-08-01 00:00')
        ->and(StatusRange::Year->firstBar($now)->format('Y-m-d H:i'))->toBe('2025-10-31 00:00');
});

test('uptime counts slow checks as up and never rounds an outage up to 100%', function (): void {
    expect(Availability::uptime([
        '2026-10-15' => ['up' => 9_998, 'slow' => 1],
        '2026-10-16' => ['down' => 1],
    ]))->toBe(99.99)
        ->and(Availability::uptime([]))->toBeNull()
        ->and(Availability::uptime(['2026-10-16' => ['up' => 4]]))->toBe(100.0);
});

test('a right answer that takes too long is slow', function (): void {
    expect(ProbeResult::answered(400)->state)->toBe(ServiceState::Up)
        ->and(ProbeResult::answered(ProbeResult::SLOW_MILLISECONDS + 1)->state)->toBe(ServiceState::Slow)
        ->and(ProbeResult::answered(9_000, ServiceState::Waiting)->state)->toBe(ServiceState::Waiting);
});

test('a rider reads each state in plain words', function (): void {
    expect(StatusComponent::Routing->describe(ServiceState::Down))->toBe('Route drawing is down right now')
        ->and(StatusComponent::Places->describe(ServiceState::Slow))->toBe('Place search is slow right now')
        ->and(StatusComponent::GarminStore->describe(ServiceState::Waiting))->toBe('The Garmin app is waiting for Garmin to approve it')
        ->and(StatusComponent::Routing->plannerNeeds())->toBeTrue()
        ->and(StatusComponent::GarminStore->plannerNeeds())->toBeFalse();
});
