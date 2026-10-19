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

    /** @var list<'report'|'visits'> Which method each question in `$asked` came through. */
    public array $methods = [];

    /**
     * @param  array<string, TrafficReport|null>  $days  The answer of each day, as Y-m-d. Null means Cloudflare
     *                                                   did not answer.
     * @param  list<TrafficReport|null>  $answers  The answers to give in turn to questions not in `$days`, before
     *                                             `$trafficReport`. Null means Cloudflare did not answer.
     * @param  bool|null  $readsVisits  Whether it has the ids for visits. Null means it does when it is connected.
     */
    public function __construct(
        private readonly array $days = [],
        private readonly ?TrafficReport $trafficReport = null,
        private readonly bool $connected = true,
        private array $answers = [],
        private readonly ?bool $readsVisits = null,
    ) {}

    public function connected(): bool
    {
        return $this->connected;
    }

    public function readsVisits(): bool
    {
        return $this->readsVisits ?? $this->connected;
    }

    public function report(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        $this->methods[] = 'report';

        return $this->answer($since, $until);
    }

    public function visits(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        $this->methods[] = 'visits';
        $trafficReport = $this->answer($since, $until);

        return new TrafficReport([], $trafficReport->visitDays, $trafficReport->topPages, $trafficReport->topReferrers, $trafficReport->countries);
    }

    private function answer(DateTimeImmutable $since, DateTimeImmutable $until): TrafficReport
    {
        $dateTimeZone = new DateTimeZone('UTC');
        $this->asked[] = ['since' => $since->setTimezone($dateTimeZone)->format('Y-m-d H:i:s'), 'until' => $until->setTimezone($dateTimeZone)->format('Y-m-d H:i:s')];
        $date = $since->setTimezone($dateTimeZone)->format('Y-m-d');

        if (array_key_exists($date, $this->days)) {
            return $this->days[$date] ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        if ($this->answers !== []) {
            return array_shift($this->answers) ?? throw new TrafficUnavailable('Cloudflare did not answer.');
        }

        return $this->trafficReport ?? new TrafficReport([], [], [], [], []);
    }
}
