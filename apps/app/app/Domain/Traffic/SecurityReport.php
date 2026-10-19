<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * What Cloudflare stopped over the last 30 days: threats by day and country, and each firewall block, added up by
 * rule, country, path, and user agent. Turnstile covers the last 7 days, the most it answers for at once.
 *
 * @phpstan-import-type Ranked from TrafficReport
 *
 * @phpstan-type DayCount array{date: string, count: int}
 * @phpstan-type PeopleBlock array{path: string, method: string, rule: string, source: string, count: int}
 */
final readonly class SecurityReport
{
    public const int DAYS = 30;

    public const int TURNSTILE_DAYS = 7;

    /** The most firewall events Cloudflare lists in one answer. */
    public const int EVENT_LIMIT = 10_000;

    /**
     * @param  list<DayCount>  $threatDays
     * @param  list<Ranked>  $threatCountries  country codes, such as BE
     * @param  list<DayCount>  $blockDays  firewall events each day, counted in full
     * @param  int  $events  how many single firewall events were read for the lists below
     * @param  list<Ranked>  $sources  the kind of rule, such as firewallCustom
     * @param  list<Ranked>  $actions  what the rule did, such as block
     * @param  list<Ranked>  $countries  country codes
     * @param  list<Ranked>  $paths
     * @param  list<Ranked>  $userAgents
     * @param  list<Ranked>  $rules  rule ids
     * @param  list<PeopleBlock>  $peoplePages  requests for pages people use that a rule stopped, which may be real
     *                                          visitors, by path, method, and rule: its description, or its id without one
     * @param  list<Ranked>  $turnstile  sign-up checks by Turnstile's event type, such as challenge_issued
     */
    public function __construct(
        public array $threatDays = [],
        public array $threatCountries = [],
        public array $blockDays = [],
        public int $events = 0,
        public array $sources = [],
        public array $actions = [],
        public array $countries = [],
        public array $paths = [],
        public array $userAgents = [],
        public array $rules = [],
        public array $peoplePages = [],
        public array $turnstile = [],
    ) {}
}
