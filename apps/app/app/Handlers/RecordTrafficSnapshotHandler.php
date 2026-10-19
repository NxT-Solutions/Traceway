<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Domain\Traffic\EmptyDay;
use App\Domain\Traffic\TrafficDay;
use App\Domain\Traffic\TrafficSnapshots;
use App\Domain\Traffic\TrafficSource;
use App\Domain\Traffic\TrafficUnavailable;
use App\Domain\Traffic\VisitWindow;
use Carbon\CarbonImmutable;

final readonly class RecordTrafficSnapshotHandler
{
    /** The Traffic page shows a month. A season is kept, then forgotten. */
    public const int KEEP_DAYS = 90;

    /** A day is kept this long after it ends, once Cloudflare has counted its last page loads. */
    public const int SETTLE_HOURS = 3;

    public function __construct(
        private TrafficSource $trafficSource,
        private TrafficSnapshots $trafficSnapshots,
    ) {}

    /**
     * Asks for the visits of each settled day of the visit window that is not kept yet. A day Cloudflare does not
     * answer in full is asked again on the next run, until it leaves the window. A day that comes back with nothing
     * at all is kept with no page loads once it came back empty `EmptyDay::ANSWERS` times, hours apart, and is not
     * asked again. Any page, referrer, or country in an answer means the day had page loads, so its count starts over.
     *
     * @return int How many days were kept.
     */
    public function handle(): int
    {
        if (! $this->trafficSource->readsVisits()) {
            return 0;
        }

        $now = now()->utc()->toImmutable();
        $hour = $now->startOfHour()->toDateTimeImmutable();
        $first = CarbonImmutable::instance(VisitWindow::start($now->toDateTimeImmutable()));
        $last = $now->subHours(self::SETTLE_HOURS)->startOfDay()->subDay();
        $kept = array_map(fn (TrafficDay $trafficDay): string => $trafficDay->date, $this->trafficSnapshots->between($first->toDateTimeImmutable(), $last->toDateTimeImmutable()));
        $emptyDays = [];

        foreach ($this->trafficSnapshots->emptyDays($first->toDateTimeImmutable(), $last->toDateTimeImmutable()) as $emptyDay) {
            $emptyDays[$emptyDay->date] = $emptyDay;
        }

        $saved = 0;

        for ($day = $first; $day <= $last; $day = $day->addDay()) {
            $date = $day->toDateString();

            if (in_array($date, $kept, strict: true)) {
                continue;
            }

            try {
                $trafficReport = $this->trafficSource->visits($day->toDateTimeImmutable(), $day->endOfDay()->toDateTimeImmutable());
            } catch (TrafficUnavailable) {
                continue;
            }

            if ($trafficReport->visitsEmpty()) {
                $emptyDay = isset($emptyDays[$date]) ? $emptyDays[$date]->answeredAgain($hour) : EmptyDay::first($date, $hour);

                if ($emptyDay->settled()) {
                    $this->trafficSnapshots->save($emptyDay->trafficDay());
                    $this->trafficSnapshots->forgetEmpty($date);
                    $saved++;
                } else {
                    $this->trafficSnapshots->saveEmpty($emptyDay);
                }

                continue;
            }

            if (isset($emptyDays[$date])) {
                $this->trafficSnapshots->forgetEmpty($date);
            }

            $trafficDay = TrafficDay::fromReport($day->toDateTimeImmutable(), $trafficReport);

            if ($trafficDay->complete()) {
                $this->trafficSnapshots->save($trafficDay);
                $saved++;
            }
        }

        $this->trafficSnapshots->forgetBefore($now->subDays(self::KEEP_DAYS)->startOfDay()->toDateTimeImmutable());

        return $saved;
    }
}
