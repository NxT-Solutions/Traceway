<?php

declare(strict_types=1);

use App\Domain\Status\Availability;
use App\Domain\Status\ProbeResult;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use Carbon\CarbonImmutable;

test('each day shows the worst state it had, and a day with no checks shows none', function (): void {
    $days = Availability::days([
        '2026-10-14' => ['up' => 280, 'slow' => 8],
        '2026-10-16' => ['up' => 100, 'down' => 2, 'waiting' => 1],
    ], CarbonImmutable::parse('2026-10-16'), 3);

    expect($days)->toBe([
        ['date' => '2026-10-14', 'state' => ServiceState::Slow],
        ['date' => '2026-10-15', 'state' => null],
        ['date' => '2026-10-16', 'state' => ServiceState::Down],
    ]);
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
