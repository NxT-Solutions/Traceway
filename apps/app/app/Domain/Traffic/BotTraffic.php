<?php

declare(strict_types=1);

namespace App\Domain\Traffic;

/**
 * What search engines, AI assistants, and scanners asked of traceway.online over the last 30 days, the longest
 * Cloudflare keeps single requests on the free plan. The totals come from their own counts, so a long list cut
 * short by Cloudflare's row limit never lowers them.
 *
 * @phpstan-import-type Ranked from TrafficReport
 *
 * @phpstan-type Read array{path: string, method: string, status: int, count: int}
 * @phpstan-type StatusCount array{status: int, count: int}
 */
final readonly class BotTraffic
{
    public const int DAYS = 30;

    /**
     * @param  list<Ranked>  $verified  requests from bots Cloudflare verified, by kind, such as AI Search
     * @param  list<Read>  $reads  requests for what Traceway publishes for assistants, such as llms.txt and /mcp
     * @param  int  $readsTotal  all of those requests
     * @param  list<Ranked>  $readers  who sent them: a verified kind, a bot's name, or other clients
     * @param  list<Ranked>  $impostors  requests that gave a bot's name Cloudflare could not verify, by that name
     * @param  int  $impostorsTotal  all of those requests
     * @param  int  $impostorsBlocked  how many of them Cloudflare turned away
     * @param  list<StatusCount>  $probes  requests for secret files, by the status they got
     * @param  list<Ranked>  $exposed  secret-file paths that were answered with a file
     */
    public function __construct(
        public array $verified = [],
        public array $reads = [],
        public int $readsTotal = 0,
        public array $readers = [],
        public array $impostors = [],
        public int $impostorsTotal = 0,
        public int $impostorsBlocked = 0,
        public array $probes = [],
        public array $exposed = [],
    ) {}
}
