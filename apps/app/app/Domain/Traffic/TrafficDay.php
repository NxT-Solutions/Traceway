<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The Web Analytics totals of one finished UTC day, kept so the Traffic page can show it after Cloudflare stops
 * answering it in full. Counts only, never a person.
 *
 * @phpstan-import-type VisitDay from TrafficReport
 * @phpstan-import-type Ranked from TrafficReport
 */
final readonly class TrafficDay
{
    /**
     * @param  string  $date  The UTC day, as Y-m-d.
     * @param  list<Ranked>  $topPages
     * @param  list<Ranked>  $topReferrers
     * @param  list<Ranked>  $countries
     */
    public function __construct(
        public string $date,
        public int $pageLoads,
        public int $visits,
        public array $topPages,
        public array $topReferrers,
        public array $countries,
    ) {}

    /**
     * The day out of a report that covers only that day.
     */
    public static function fromReport(DateTimeImmutable $day, TrafficReport $trafficReport): self
    {
        $date = $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $visitDays = array_values(array_filter($trafficReport->visitDays, fn (array $visitDay): bool => $visitDay['date'] === $date));

        return new self(
            $date,
            array_sum(array_column($visitDays, 'page_loads')),
            array_sum(array_column($visitDays, 'visits')),
            $trafficReport->topPages,
            $trafficReport->topReferrers,
            $trafficReport->countries,
        );
    }

    /**
     * Whether the answer holds the whole day. Cloudflare sometimes leaves one of the lists empty with no error, and
     * a day that had page loads always has a page, a referrer, and a country.
     */
    public function complete(): bool
    {
        return $this->pageLoads > 0 && $this->topPages !== [] && $this->topReferrers !== [] && $this->countries !== [];
    }

    /**
     * @return VisitDay
     */
    public function visitDay(): array
    {
        return ['date' => $this->date, 'page_loads' => $this->pageLoads, 'visits' => $this->visits];
    }
}
