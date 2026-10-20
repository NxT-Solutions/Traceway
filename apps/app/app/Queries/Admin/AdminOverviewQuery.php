<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Account\ConnectionProvider;
use App\Domain\Routing\Sport;
use App\Domain\Usage\UsageMetric;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The admin area's first page: how many riders there are, how many come back, and what they use. Every number is
 * a total. It is built again every five minutes.
 *
 * @phpstan-type Day array{date: string, count: int}
 * @phpstan-type ActiveDay array{date: string, day: int, week: int, month: int}
 * @phpstan-type Ranked array{label: string, count: int}
 * @phpstan-type Usage array{metric: string, label: string, total: int, days: list<Day>}
 * @phpstan-type Totals array{riders: int, verified: int, two_factor: int, new_week: int, new_month: int, active_day: int, active_week: int, active_month: int, online_now: int, saved_routes: int, routes_month: int, watches: int, watches_active: int, assistant_riders: int, service_accounts: int}
 * @phpstan-type Page array{generated_at: string, days: int, totals: Totals, signups: list<Day>, saved: list<Day>, active: list<ActiveDay>, usage: list<Usage>, sports: list<Ranked>, sends: list<Ranked>, assistants: list<Ranked>}
 */
final readonly class AdminOverviewQuery
{
    /** The charts cover this many days. */
    public const int DAYS = 90;

    /** The usage table covers this many days. */
    public const int USAGE_DAYS = 30;

    /** A session that did something this recently counts as online now. */
    private const int ONLINE_SECONDS = 300;

    public function __construct(private CacheRepository $cacheRepository) {}

    /**
     * @return Page
     */
    public function page(): array
    {
        return $this->cacheRepository->remember('admin-overview:'.intdiv(now()->getTimestamp(), 300), 300, fn (): array => $this->build());
    }

    /**
     * @return Page
     */
    private function build(): array
    {
        $now = CarbonImmutable::now();
        $since = $now->subDays(self::DAYS - 1)->startOfDay();

        return [
            'generated_at' => $now->toIso8601String(),
            'days' => self::DAYS,
            'totals' => $this->totals($now),
            'signups' => $this->perDay('users', $since, $now),
            'saved' => $this->perDay('saved_routes', $since, $now),
            'active' => $this->active($since, $now),
            'usage' => $this->usage($now),
            'sports' => $this->sports(),
            'sends' => $this->sends(),
            'assistants' => $this->assistants($now),
        ];
    }

    /**
     * @return Totals
     */
    private function totals(CarbonImmutable $now): array
    {
        $builder = DB::table('sessions')->where('last_activity', '>=', $now->getTimestamp() - self::ONLINE_SECONDS);

        return [
            'riders' => DB::table('users')->count(),
            'verified' => DB::table('users')->whereNotNull('email_verified_at')->count(),
            'two_factor' => DB::table('users')->whereNotNull('two_factor_confirmed_at')->count(),
            'new_week' => DB::table('users')->where('created_at', '>=', $now->subDays(7))->count(),
            'new_month' => DB::table('users')->where('created_at', '>=', $now->subDays(30))->count(),
            'active_day' => DB::table('users')->where('last_seen_at', '>=', $now->subDay())->count(),
            'active_week' => DB::table('users')->where('last_seen_at', '>=', $now->subDays(7))->count(),
            'active_month' => DB::table('users')->where('last_seen_at', '>=', $now->subDays(30))->count(),
            'online_now' => $builder->whereNotNull('user_id')->distinct()->count('user_id'),
            'saved_routes' => DB::table('saved_routes')->count(),
            'routes_month' => DB::table('saved_routes')->where('created_at', '>=', $now->subDays(30))->count(),
            'watches' => DB::table('device_links')->whereNotNull('user_id')->whereNotNull('linked_at')->count(),
            'watches_active' => DB::table('device_links')->whereNotNull('user_id')->where('last_used_at', '>=', $now->subDays(30))->count(),
            'assistant_riders' => $this->liveTokens($now)->distinct()->count('oauth_access_tokens.user_id'),
            'service_accounts' => DB::table('connected_accounts')->count(),
        ];
    }

    /**
     * @return list<Day>
     */
    private function perDay(string $table, CarbonImmutable $since, CarbonImmutable $now): array
    {
        $counts = DB::table($table)
            ->where('created_at', '>=', $since)
            ->selectRaw("strftime('%Y-%m-%d', created_at) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        return $this->fill($counts, $since, $now);
    }

    /**
     * @return list<ActiveDay>
     */
    private function active(CarbonImmutable $since, CarbonImmutable $now): array
    {
        $gauges = [
            UsageMetric::ActiveRidersDay->value => 'day',
            UsageMetric::ActiveRidersWeek->value => 'week',
            UsageMetric::ActiveRidersMonth->value => 'month',
        ];
        $rows = [];

        foreach (DB::table('usage_counters')->whereIn('metric', array_keys($gauges))->where('day', '>=', $since->toDateString())->get(['day', 'metric', 'count']) as $row) {
            if (is_string($row->day) && is_string($row->metric) && is_numeric($row->count)) {
                $rows[$row->day][$gauges[$row->metric] ?? 'day'] = (int) $row->count;
            }
        }

        // Today's gauge is written just before midnight, so until then today shows the riders as they stand.
        $today = $now->toDateString();
        $rows[$today] = [
            'day' => DB::table('users')->where('last_seen_at', '>=', $now->subDay())->count(),
            'week' => DB::table('users')->where('last_seen_at', '>=', $now->subDays(7))->count(),
            'month' => DB::table('users')->where('last_seen_at', '>=', $now->subDays(30))->count(),
        ];

        $days = [];

        for ($day = $since; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $date,
                'day' => $rows[$date]['day'] ?? 0,
                'week' => $rows[$date]['week'] ?? 0,
                'month' => $rows[$date]['month'] ?? 0,
            ];
        }

        return $days;
    }

    /**
     * @return list<Usage>
     */
    private function usage(CarbonImmutable $now): array
    {
        $since = $now->subDays(self::USAGE_DAYS - 1)->startOfDay();
        $counts = [];

        foreach (DB::table('usage_counters')->where('day', '>=', $since->toDateString())->get(['day', 'metric', 'count']) as $row) {
            if (is_string($row->day) && is_string($row->metric) && is_numeric($row->count)) {
                $counts[$row->metric][$row->day] = (int) $row->count;
            }
        }

        return array_map(function (UsageMetric $usageMetric) use ($counts, $since, $now): array {
            $days = $this->fill($counts[$usageMetric->value] ?? [], $since, $now);

            return [
                'metric' => $usageMetric->value,
                'label' => $usageMetric->label(),
                'total' => array_sum(array_column($days, 'count')),
                'days' => $days,
            ];
        }, UsageMetric::counts());
    }

    /**
     * @return list<Ranked>
     */
    private function sports(): array
    {
        $ranked = [];

        foreach (DB::table('saved_routes')->selectRaw('sport, count(*) as total')->groupBy('sport')->orderByDesc('total')->get() as $row) {
            $sport = is_string($row->sport) ? Sport::tryFrom($row->sport) : null;
            $ranked[] = [
                'label' => match ($sport) {
                    Sport::Road => 'Road',
                    Sport::Gravel => 'Gravel',
                    Sport::Mtb => 'Mountain bike',
                    Sport::Run => 'Run',
                    Sport::TrailRun => 'Trail run',
                    Sport::Walk => 'Walk',
                    Sport::Hike => 'Hike',
                    default => is_string($row->sport) ? $row->sport : 'Other',
                },
                'count' => is_numeric($row->total) ? (int) $row->total : 0,
            ];
        }

        return $ranked;
    }

    /**
     * @return list<Ranked>
     */
    private function sends(): array
    {
        $ranked = [];

        foreach (DB::table('sent_routes')->selectRaw('service, count(*) as total')->groupBy('service')->orderByDesc('total')->get() as $row) {
            $ranked[] = [
                'label' => is_string($row->service) ? (ConnectionProvider::tryFrom($row->service)?->label() ?? $row->service) : 'Other',
                'count' => is_numeric($row->total) ? (int) $row->total : 0,
            ];
        }

        return $ranked;
    }

    /**
     * How many riders each AI assistant can act for right now.
     *
     * @return list<Ranked>
     */
    private function assistants(CarbonImmutable $now): array
    {
        $ranked = [];

        foreach ($this->liveTokens($now)
            ->join('oauth_clients', 'oauth_clients.id', '=', 'oauth_access_tokens.client_id')
            ->selectRaw('oauth_clients.name as name, count(distinct oauth_access_tokens.user_id) as total')
            ->groupBy('oauth_clients.name')
            ->orderByDesc('total')
            ->get() as $row) {
            $ranked[] = [
                'label' => is_string($row->name) && $row->name !== '' ? $row->name : 'Unnamed app',
                'count' => is_numeric($row->total) ? (int) $row->total : 0,
            ];
        }

        return $ranked;
    }

    private function liveTokens(CarbonImmutable $now): Builder
    {
        return DB::table('oauth_access_tokens')
            ->whereNotNull('oauth_access_tokens.user_id')
            ->where('oauth_access_tokens.revoked', operator: false)
            ->where(fn (Builder $builder): Builder => $builder->whereNull('oauth_access_tokens.expires_at')->orWhere('oauth_access_tokens.expires_at', '>', $now));
    }

    /**
     * Every day in the stretch, with nothing on the days nothing happened.
     *
     * @param  array<mixed>  $counts
     * @return list<Day>
     */
    private function fill(array $counts, CarbonImmutable $since, CarbonImmutable $now): array
    {
        $days = [];

        for ($day = $since; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $date = $day->toDateString();
            $count = $counts[$date] ?? 0;
            $days[] = ['date' => $date, 'count' => is_numeric($count) ? (int) $count : 0];
        }

        return $days;
    }
}
