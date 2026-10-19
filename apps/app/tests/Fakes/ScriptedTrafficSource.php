<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Traffic\TrafficReport;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A traffic source that answers each UTC day it is asked for from a script, and remembers what it was asked.
 */
final class ScriptedTrafficSource implements TrafficSource
{
    /** @var list<array{since: string, until: string}> */
    public array $asked = [];

    /**
     * @param  array<string, TrafficReport|null>  $days  The answer of each day, as Y-m-d. Null means Cloudflare
     *                                                   did not answer.
     */
    public function __construct(
        private readonly array $days = [],
        private readonly ?TrafficReport $trafficReport = null,
        private readonly bool $connected = true,
    ) {}

    public function connected(): bool
    {
        return $this->connected;
    }

    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        $dateTimeZone = new DateTimeZone('UTC');
        $this->asked[] = ['since' => $since->setTimezone($dateTimeZone)->format('Y-m-d H:i:s'), 'until' => $until->setTimezone($dateTimeZone)->format('Y-m-d H:i:s')];
        $date = $since->setTimezone($dateTimeZone)->format('Y-m-d');

        if (array_key_exists($date, $this->days)) {
            return $this->days[$date] ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        return $this->trafficReport ?? new TrafficReport([], [], [], [], []);
    }
}
