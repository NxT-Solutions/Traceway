<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * Every request that reached traceway.online, by hour for the last three days, by day for a year, and by kind for
 * the last 30 days. Cloudflare keeps hours for three days and days for a year on the free plan.
 *
 * @phpstan-import-type Ranked from TrafficReport
 * @phpstan-import-type StatusCount from BotTraffic
 *
 * @phpstan-type Hour array{time: string, requests: int, threats: int}
 * @phpstan-type Day array{date: string, requests: int, threats: int}
 */
final readonly class RequestBreakdown
{
    public const int HOURS = 72;

    public const int YEAR_DAYS = 365;

    public const int DAYS = 30;

    /**
     * @param  list<Hour>  $hours
     * @param  list<Day>  $days  a year of days, oldest first
     * @param  list<StatusCount>  $statuses  the answers of the last 30 days, by status
     * @param  list<Ranked>  $contentTypes  the answers of the last 30 days, by kind of file
     * @param  list<Ranked>  $browsers  the page views of the last 30 days, by browser family, bots included
     * @param  list<Ranked>  $httpVersions  the requests of the last 30 days, by HTTP version
     * @param  list<Ranked>  $ipClasses  the requests of the last 30 days, by what Cloudflare knows of the address
     */
    public function __construct(
        public array $hours = [],
        public array $days = [],
        public array $statuses = [],
        public array $contentTypes = [],
        public array $browsers = [],
        public array $httpVersions = [],
        public array $ipClasses = [],
    ) {}
}
