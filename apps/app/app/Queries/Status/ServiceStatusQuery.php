<?php

declare(strict_types=1);

namespace App\Queries\Status;

use App\Domain\Status\Availability;
use App\Domain\Status\ServiceProbe;
use App\Domain\Status\ServiceProbes;
use App\Domain\Status\ServiceState;
use App\Domain\Status\StatusComponent;
use App\Domain\Status\StatusGroup;
use App\Queries\RowReader;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What the status page and the planner's banner show: the last answer of each check, the
 * worst state of each of the last 90 days, and the notices posted by hand.
 *
 * @phpstan-type Day array{date: string, state: string|null}
 * @phpstan-type Component array{key: string, label: string, state: string|null, summary: string, latency_ms: int|null, uptime: float|null, days: list<Day>}
 * @phpstan-type Group array{key: string, label: string, components: list<Component>}
 * @phpstan-type Notice array{id: int, title: string, body: string|null, components: list<string>, started_at: string|null, resolved_at: string|null}
 * @phpstan-type Page array{state: string, headline: string, checked_at: string|null, stale: bool, notices: list<Notice>, groups: list<Group>}
 */
final readonly class ServiceStatusQuery
{
    /** With no check this long, the scheduler has stopped and the page says so. */
    public const int STALE_MINUTES = 15;

    public const int DAYS = 90;

    /** A notice stays on the page this long after it is resolved. */
    public const int RESOLVED_DAYS = 7;

    public function __construct(
        private ServiceProbes $serviceProbes,
        private CacheRepository $cacheRepository,
    ) {}

    /**
     * The status page. It is built again after a new check or a notice, and every five
     * minutes, so it notices when the checks stop.
     *
     * @return Page
     */
    public function page(): array
    {
        $stamp = json_encode([
            DB::table('status_checks')->max('checked_at'),
            DB::table('status_notices')->max('updated_at'),
            DB::table('status_notices')->count(),
            DB::table('status_notices')->whereNull('resolved_at')->count(),
            intdiv(now()->getTimestamp(), 300),
            $this->components(),
        ], JSON_THROW_ON_ERROR);

        return $this->cacheRepository->remember('status-page:'.md5($stamp), 300, fn (): array => $this->build());
    }

    /**
     * The line on the map when the planner cannot work as it should. A notice about the map,
     * or about everything, comes first. Then a map service that is slow or down, so a notice
     * about something else never hides it. Then any other open notice. Null when all is well.
     *
     * @return array{title: string, body: string|null}|null
     */
    public function banner(): ?array
    {
        $notices = $this->notices(openOnly: true);
        $aboutTheMap = array_find($notices, fn (array $notice): bool => $notice['components'] === [] || array_any(
            $notice['components'],
            fn (string $component): bool => StatusComponent::tryFrom($component)?->plannerNeeds() === true,
        ));

        if ($aboutTheMap !== null) {
            return ['title' => $aboutTheMap['title'], 'body' => $aboutTheMap['body']];
        }

        [$latest, $checkedAt] = $this->latest();
        $fresh = $checkedAt instanceof CarbonImmutable && ! $this->isStale($checkedAt);

        foreach ($fresh ? $latest : [] as $component => $check) {
            $statusComponent = StatusComponent::tryFrom($component);

            if ($statusComponent?->plannerNeeds() === true && in_array($check['state'], [ServiceState::Slow, ServiceState::Down], strict: true)) {
                return [
                    'title' => $statusComponent->describe($check['state']).'.',
                    'body' => $check['state'] === ServiceState::Down
                        ? 'It is a problem on the map server, not on your side. Please try again in a few minutes.'
                        : 'The map server is busy. It may take longer than usual.',
                ];
            }
        }

        $other = $notices[0] ?? null;

        return $other === null ? null : ['title' => $other['title'], 'body' => $other['body']];
    }

    /**
     * Whether route drawing is down: an open notice names it, or the latest
     * check, while fresh, found it down. The planner then stops offering Draw
     * and Shuffle, which would only fail. Slow is not down: a slow loop still
     * comes back.
     */
    public function routingDown(): bool
    {
        $named = array_any(
            $this->notices(openOnly: true),
            fn (array $notice): bool => in_array(StatusComponent::Routing->value, $notice['components'], strict: true),
        );

        if ($named) {
            return true;
        }

        [$latest, $checkedAt] = $this->latest();

        if (! $checkedAt instanceof CarbonImmutable || $this->isStale($checkedAt)) {
            return false;
        }

        return ($latest[StatusComponent::Routing->value]['state'] ?? null) === ServiceState::Down;
    }

    /**
     * @return Page
     */
    private function build(): array
    {
        [$latest, $checkedAt] = $this->latest();
        $stale = $checkedAt instanceof CarbonImmutable && $this->isStale($checkedAt);
        $counts = $this->counts();
        $today = today()->toDateTimeImmutable();
        $groups = [];
        $worst = ServiceState::Up;
        $broken = [];

        foreach ($this->components() as $component) {
            $statusComponent = StatusComponent::from($component);
            $check = $stale ? null : ($latest[$component] ?? null);
            $state = $check['state'] ?? null;

            if ($state instanceof ServiceState && $state->worseThan(ServiceState::Waiting)) {
                $broken[] = $statusComponent->describe($state);
                $worst = $state->worseThan($worst) ? $state : $worst;
            }

            $group = $statusComponent->group();
            $groups[$group->value] ??= ['key' => $group->value, 'label' => $group->label(), 'components' => []];
            $groups[$group->value]['components'][] = [
                'key' => $component,
                'label' => $statusComponent->label(),
                'state' => $state?->value,
                'summary' => $state instanceof ServiceState ? $statusComponent->describe($state) : 'Not checked yet',
                'latency_ms' => $check['latency_ms'] ?? null,
                'uptime' => Availability::uptime($counts[$component] ?? []),
                'days' => array_map(
                    fn (array $day): array => ['date' => $day['date'], 'state' => $day['state']?->value],
                    Availability::days($counts[$component] ?? [], $today, self::DAYS),
                ),
            ];
        }

        $notices = $this->notices(openOnly: false);
        $openNotice = array_find($notices, fn (array $notice): bool => $notice['resolved_at'] === null);

        // A notice posted by hand says more than a check that still answers.
        if ($openNotice !== null && $broken === [] && ServiceState::Slow->worseThan($worst)) {
            $worst = ServiceState::Slow;
        }

        $headline = match (true) {
            ! $checkedAt instanceof CarbonImmutable => 'No checks yet',
            $stale => 'Checks have stopped, so this page may be out of date',
            $openNotice !== null && $broken === [] => $openNotice['title'],
            count($broken) === 1 => $broken[0],
            $broken !== [] => 'Some parts of Traceway are not working right now',
            default => 'Everything works',
        };

        return [
            'state' => ! $checkedAt instanceof CarbonImmutable || $stale ? 'unknown' : $worst->value,
            'headline' => $headline,
            'checked_at' => $checkedAt?->toIso8601String(),
            'stale' => $stale,
            'notices' => $notices,
            'groups' => array_values(array_filter(
                array_map(fn (StatusGroup $statusGroup): ?array => $groups[$statusGroup->value] ?? null, StatusGroup::cases()),
            )),
        ];
    }

    /**
     * The answer of each check in the last run, and when that run was. A run saves all its
     * checks at once, under one time.
     *
     * @return array{0: array<string, array{state: ServiceState, latency_ms: int}>, 1: CarbonImmutable|null}
     */
    private function latest(): array
    {
        $newest = DB::table('status_checks')->max('checked_at');

        if (! is_string($newest)) {
            return [[], null];
        }

        $rows = DB::table('status_checks')
            ->where('checked_at', $newest)
            ->orderBy('id')
            ->get(['component', 'state', 'latency_ms']);

        $latest = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $state = ServiceState::tryFrom($reader->string('state'));

            if ($state instanceof ServiceState) {
                $latest[$reader->string('component')] = ['state' => $state, 'latency_ms' => $reader->int('latency_ms')];
            }
        }

        return [$latest, CarbonImmutable::parse($newest)];
    }

    /**
     * How many checks of each component ended in each state, per day.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    private function counts(): array
    {
        $rows = DB::table('status_checks')
            ->where('checked_at', '>=', now()->subDays(self::DAYS - 1)->startOfDay())
            ->selectRaw('component, date(checked_at) as day, state, count(*) as checks')
            ->groupBy('component', 'day', 'state')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $counts[$reader->string('component')][$reader->string('day')][$reader->string('state')] = $reader->int('checks');
        }

        return $counts;
    }

    /**
     * @return list<Notice>
     */
    private function notices(bool $openOnly): array
    {
        $rows = DB::table('status_notices')
            ->where(fn (Builder $builder) => $openOnly
                ? $builder->whereNull('resolved_at')
                : $builder->whereNull('resolved_at')->orWhere('resolved_at', '>=', now()->subDays(self::RESOLVED_DAYS)))
            ->orderByRaw('resolved_at is not null')
            ->latest('started_at')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'title', 'body', 'components', 'started_at', 'resolved_at']);

        $notices = [];

        foreach ($rows as $row) {
            $reader = new RowReader($row);
            $notices[] = [
                'id' => $reader->int('id'),
                'title' => $reader->string('title'),
                'body' => $reader->optionalString('body'),
                'components' => $this->strings($reader->optionalString('components')),
                'started_at' => $reader->iso('started_at'),
                'resolved_at' => $reader->iso('resolved_at'),
            ];
        }

        return $notices;
    }

    /**
     * The components Traceway checks, in the order the page lists them.
     *
     * @return list<string>
     */
    private function components(): array
    {
        $checked = array_map(fn (ServiceProbe $serviceProbe): string => $serviceProbe->component()->value, $this->serviceProbes->all());

        return array_values(array_filter(
            array_map(fn (StatusComponent $statusComponent): string => $statusComponent->value, StatusComponent::cases()),
            fn (string $component): bool => in_array($component, $checked, strict: true),
        ));
    }

    /**
     * @return list<string>
     */
    private function strings(?string $json): array
    {
        $values = json_decode($json ?? '[]', associative: true);

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    private function isStale(CarbonImmutable $checkedAt): bool
    {
        return $checkedAt->lt(now()->subMinutes(self::STALE_MINUTES));
    }
}
