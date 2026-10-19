<?php

declare(strict_types=1);

namespace App\Handlers;

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

    public function __construct(
        private TrafficSource $trafficSource,
        private TrafficSnapshots $trafficSnapshots,
    ) {}

    /**
     * Asks for each finished day of the visit window that is not kept yet. A day Cloudflare does not answer in full
     * is asked again on the next run, until it leaves the window.
     *
     * @return int How many days were kept.
     */
    public function handle(): int
    {
        if (! $this->trafficSource->connected()) {
            return 0;
        }

        $now = now()->utc()->toImmutable();
        $first = CarbonImmutable::instance(VisitWindow::start($now->toDateTimeImmutable()));
        $yesterday = $now->startOfDay()->subDay();
        $kept = array_map(fn (TrafficDay $trafficDay): string => $trafficDay->date, $this->trafficSnapshots->between($first->toDateTimeImmutable(), $yesterday->toDateTimeImmutable()));
        $saved = 0;

        for ($day = $first; $day <= $yesterday; $day = $day->addDay()) {
            if (in_array($day->toDateString(), $kept, strict: true)) {
                continue;
            }

            try {
                $trafficReport = $this->trafficSource->report($day->toDateTimeImmutable(), $day->endOfDay()->toDateTimeImmutable());
            } catch (TrafficUnavailable) {
                continue;
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
