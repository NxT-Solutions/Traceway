import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useHydrated } from '@/hooks/use-hydrated';
import { cn } from '@/lib/utils';
import { dashboard, status } from '@/routes';

type State = 'up' | 'waiting' | 'slow' | 'down';

/** One hour, day, or week, by its start in UTC, with the worst state in it. */
type Bar = { start: string; state: State | null };

export type Range = 'day' | 'week' | 'month' | 'quarter' | 'year';

type Component = {
    key: string;
    label: string;
    state: State | null;
    summary: string;
    latency_ms: number | null;
    uptime: number | null;
    bars: Bar[];
};

type Group = { key: string; label: string; components: Component[] };

type Notice = {
    id: number;
    title: string;
    body: string | null;
    components: string[];
    started_at: string | null;
    resolved_at: string | null;
};

export type StatusPage = {
    state: State | 'unknown';
    headline: string;
    checked_at: string | null;
    stale: boolean;
    range: Range;
    notices: Notice[];
    groups: Group[];
};

type Unit = 'hour' | 'day' | 'week';

/**
 * How each range reads. The page opens on the last 24 hours, so an outage
 * this morning shows as its hours. A phone shows at most the last 30 bars.
 */
const ranges: Record<
    Range,
    {
        label: string;
        last: string;
        unit: Unit;
        from: string;
        fromPhone: string;
        to: string;
    }
> = {
    day: {
        label: '24 hours',
        last: 'the last 24 hours',
        unit: 'hour',
        from: '24 hours ago',
        fromPhone: '24 hours ago',
        to: 'Now',
    },
    week: {
        label: '7 days',
        last: 'the last 7 days',
        unit: 'day',
        from: '7 days ago',
        fromPhone: '7 days ago',
        to: 'Today',
    },
    month: {
        label: '30 days',
        last: 'the last 30 days',
        unit: 'day',
        from: '30 days ago',
        fromPhone: '30 days ago',
        to: 'Today',
    },
    quarter: {
        label: '90 days',
        last: 'the last 90 days',
        unit: 'day',
        from: '90 days ago',
        fromPhone: '30 days ago',
        to: 'Today',
    },
    year: {
        label: 'Year',
        last: 'the last year',
        unit: 'week',
        from: 'A year ago',
        fromPhone: '30 weeks ago',
        to: 'This week',
    },
};

const rangeOrder: Range[] = ['day', 'week', 'month', 'quarter', 'year'];

const PHONE_BARS = 30;

const stateWord: Record<State, string> = {
    up: 'Works',
    waiting: 'Waiting',
    slow: 'Slow',
    down: 'Down',
};

// The dot and the day bars. Each state also has a word, so colour is never
// the only sign.
const stateColour: Record<State | 'unknown', string> = {
    up: 'bg-primary',
    waiting: 'bg-muted-foreground/60',
    slow: 'bg-warning',
    down: 'bg-down',
    unknown: 'bg-border',
};

const outsideStatus = [
    { label: 'Cloudflare', href: 'https://www.cloudflarestatus.com' },
    { label: 'Garmin Connect', href: 'https://connect.garmin.com/status/' },
] as const;

// The server draws this page first, in its own language and time zone. Until
// the browser has taken it over, times come from the data alone, in UTC, so
// both write the same words. Then they turn into the reader's own time.
const utc = {
    locale: 'en-GB',
    timeZone: 'UTC',
    timeZoneName: 'short',
} as const;

function ago(iso: string | null, hydrated: boolean): string {
    if (iso === null) {
        return 'Not checked yet';
    }

    if (!hydrated) {
        return `Checked at ${new Date(iso).toLocaleTimeString(utc.locale, {
            hour: '2-digit',
            minute: '2-digit',
            timeZone: utc.timeZone,
            timeZoneName: utc.timeZoneName,
        })}`;
    }

    const minutes = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 60_000),
    );

    if (minutes < 1) {
        return 'Checked just now';
    }

    if (minutes < 60) {
        return `Checked ${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    }

    const hours = Math.round(minutes / 60);

    return `Checked ${hours} hour${hours === 1 ? '' : 's'} ago`;
}

function when(iso: string | null, hydrated: boolean): string {
    if (iso === null) {
        return '';
    }

    return new Date(iso).toLocaleString(hydrated ? undefined : utc.locale, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        ...(hydrated
            ? {}
            : { timeZone: utc.timeZone, timeZoneName: utc.timeZoneName }),
    });
}

function Dot({
    state,
    large = false,
}: {
    state: State | 'unknown' | null;
    large?: boolean;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-block shrink-0 rounded-full',
                large ? 'size-4' : 'size-2.5',
                stateColour[state ?? 'unknown'],
            )}
        />
    );
}

/**
 * A bar's name: its hour in the reader's own time once the browser has the
 * page (in UTC until then), or the day or week it starts, which are UTC days.
 */
function barLabel(start: string, unit: Unit, hydrated: boolean): string {
    if (unit === 'hour') {
        return new Date(start).toLocaleTimeString(
            hydrated ? undefined : utc.locale,
            {
                hour: 'numeric',
                minute: '2-digit',
                ...(hydrated
                    ? {}
                    : {
                          timeZone: utc.timeZone,
                          timeZoneName: utc.timeZoneName,
                      }),
            },
        );
    }

    const day = new Date(start).toLocaleDateString(
        hydrated ? undefined : utc.locale,
        { day: 'numeric', month: 'short', timeZone: 'UTC' },
    );

    return unit === 'week' ? `the week of ${day}` : day;
}

function problemBars(
    bars: Bar[],
    state: 'down' | 'slow',
    unit: Unit,
    hydrated: boolean,
): string[] {
    return bars
        .filter((bar) => bar.state === state)
        .map((bar) => barLabel(bar.start, unit, hydrated));
}

/** "Down at 10:00", "down on 27 Oct", "down in the week of 13 Oct". */
function problemLine(word: string, items: string[], unit: Unit): string | null {
    if (items.length === 0) {
        return null;
    }

    const at = { hour: 'at', day: 'on', week: 'in' }[unit];

    return `${word} ${at} ${list(items)}.`;
}

function list(items: string[]): string {
    return items.length <= 1
        ? items.join('')
        : `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}`;
}

/**
 * The range in bars, oldest left, now right. A phone shows the last 30. The
 * bad hours, days, or weeks are also written out under the bars, so colour is
 * never the only record.
 */
function Bars({
    component,
    range,
    hydrated,
}: {
    component: Component;
    range: Range;
    hydrated: boolean;
}) {
    const { unit, last, from, fromPhone, to } = ranges[range];
    const problems = [
        problemLine(
            'Down',
            problemBars(component.bars, 'down', unit, hydrated),
            unit,
        ),
        problemLine(
            'Slow',
            problemBars(component.bars, 'slow', unit, hydrated),
            unit,
        ),
    ].filter((line) => line !== null);

    return (
        <div className="space-y-1.5">
            <div aria-hidden="true" className="flex h-8 items-end gap-px">
                {component.bars.map((bar, index) => (
                    <span
                        key={bar.start}
                        title={`${barLabel(bar.start, unit, hydrated)}: ${bar.state === null ? 'No checks' : stateWord[bar.state]}`}
                        className={cn(
                            'flex-1 rounded-[2px]',
                            bar.state === null ? 'h-1.5' : 'h-full',
                            stateColour[bar.state ?? 'unknown'],
                            index < component.bars.length - PHONE_BARS &&
                                'hidden sm:block',
                        )}
                    />
                ))}
            </div>
            <div
                aria-hidden="true"
                className="flex justify-between text-xs text-muted-foreground"
            >
                <span className="sm:hidden">{fromPhone}</span>
                <span className="hidden sm:inline">{from}</span>
                <span>{to}</span>
            </div>
            <p className="text-sm text-muted-foreground">
                {problems.length === 0
                    ? `No problems in ${last}.`
                    : problems.join(' ')}
            </p>
        </div>
    );
}

/**
 * How far back the bars go. Each choice is a link, so a range can be shared,
 * and only the bars load again.
 */
function RangePicker({ current }: { current: Range }) {
    return (
        <nav aria-label="How far back the bars go">
            <ul className="flex w-full gap-1 rounded-lg border border-border bg-card p-1 sm:w-fit">
                {rangeOrder.map((range) => (
                    <li key={range} className="flex-1 sm:flex-none">
                        <Link
                            href={status({ query: { range } })}
                            only={['range', 'groups']}
                            preserveScroll
                            aria-current={
                                range === current ? 'true' : undefined
                            }
                            className={cn(
                                'inline-flex min-h-9 w-full items-center justify-center rounded-md px-2 text-sm font-medium whitespace-nowrap outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:px-3',
                                range === current
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {ranges[range].label}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

export default function Status() {
    const page = usePage<StatusPage>().props;
    const hydrated = useHydrated();

    usePoll(60_000, {
        only: ['state', 'headline', 'checked_at', 'stale', 'notices', 'groups'],
    });

    const range = ranges[page.range] === undefined ? 'day' : page.range;

    const open = page.notices.filter((notice) => notice.resolved_at === null);
    const resolved = page.notices.filter(
        (notice) => notice.resolved_at !== null,
    );

    return (
        <>
            <Head title="Service status" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="flex items-center justify-between gap-4 px-4 py-5 sm:px-6">
                    <a
                        href="/"
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <AppLogoIcon className="size-7 text-primary" />
                        Traceway
                    </a>
                    <Link
                        href={dashboard()}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                    >
                        Open the planner
                    </Link>
                </header>

                <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-10 px-4 pt-6 pb-16 sm:px-6">
                    <section aria-labelledby="headline" className="space-y-2">
                        <p className="text-sm font-medium text-muted-foreground">
                            Service status
                        </p>
                        <h1
                            id="headline"
                            className="flex items-center gap-3 text-3xl font-semibold tracking-tight sm:text-4xl"
                        >
                            <Dot state={page.state} large />
                            {page.headline}
                        </h1>
                        <p className="text-muted-foreground">
                            {ago(page.checked_at, hydrated)}. We check every 5
                            minutes.
                        </p>
                        {/* Read out only when the headline itself changes. */}
                        <p className="sr-only" role="status">
                            {page.headline}
                        </p>
                    </section>

                    {open.length > 0 && (
                        <section
                            aria-labelledby="notices"
                            className="space-y-3"
                        >
                            <h2 id="notices" className="sr-only">
                                Notices
                            </h2>
                            {open.map((notice) => (
                                <article
                                    key={notice.id}
                                    className="rounded-xl border border-warning bg-card p-5"
                                >
                                    <h3 className="text-lg font-semibold">
                                        {notice.title}
                                    </h3>
                                    {notice.body && (
                                        <p className="mt-1 text-muted-foreground">
                                            {notice.body}
                                        </p>
                                    )}
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Since{' '}
                                        {when(notice.started_at, hydrated)}
                                    </p>
                                </article>
                            ))}
                        </section>
                    )}

                    <RangePicker current={range} />

                    {page.groups.map((group) => (
                        <section
                            key={group.key}
                            aria-labelledby={`group-${group.key}`}
                            className="space-y-3"
                        >
                            <h2
                                id={`group-${group.key}`}
                                className="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {group.label}
                            </h2>
                            <ul className="divide-y divide-border rounded-xl border border-border bg-card">
                                {group.components.map((component) => (
                                    <li
                                        key={component.key}
                                        className="space-y-3 p-4 sm:p-5"
                                    >
                                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                            <p className="flex items-center gap-2 font-medium">
                                                <Dot state={component.state} />
                                                {component.label}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {component.state === null
                                                    ? 'No answer yet'
                                                    : stateWord[
                                                          component.state
                                                      ]}
                                                {component.uptime !== null &&
                                                    component.key !==
                                                        'garmin_store' && (
                                                        <span className="tabular-nums">
                                                            {' '}
                                                            · {component.uptime}
                                                            % up
                                                        </span>
                                                    )}
                                            </p>
                                        </div>
                                        {component.state !== null &&
                                            component.state !== 'up' && (
                                                <p className="text-sm">
                                                    {component.summary}.
                                                </p>
                                            )}
                                        <Bars
                                            component={component}
                                            range={range}
                                            hydrated={hydrated}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}

                    {resolved.length > 0 && (
                        <section
                            aria-labelledby="resolved"
                            className="space-y-3"
                        >
                            <h2
                                id="resolved"
                                className="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                This week
                            </h2>
                            <ul className="space-y-3">
                                {resolved.map((notice) => (
                                    <li
                                        key={notice.id}
                                        className="rounded-xl border border-border p-4"
                                    >
                                        <p className="font-medium">
                                            {notice.title}
                                        </p>
                                        {notice.body && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {notice.body}
                                            </p>
                                        )}
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {when(notice.started_at, hydrated)}{' '}
                                            to{' '}
                                            {when(notice.resolved_at, hydrated)}
                                            . Resolved.
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section
                        aria-labelledby="legend"
                        className="space-y-3 text-sm text-muted-foreground"
                    >
                        <h2 id="legend" className="sr-only">
                            What the colours mean
                        </h2>
                        <ul className="flex flex-wrap gap-x-5 gap-y-2">
                            {(['up', 'slow', 'down', 'waiting'] as const).map(
                                (state) => (
                                    <li
                                        key={state}
                                        className="flex items-center gap-2"
                                    >
                                        <Dot state={state} />
                                        {stateWord[state]}
                                    </li>
                                ),
                            )}
                            <li className="flex items-center gap-2">
                                <Dot state={null} />
                                No checks
                            </li>
                        </ul>
                        <p>
                            Each bar is one {ranges[range].unit}, with the worst
                            moment in it. A short bar had no checks. Traceway
                            also runs on{' '}
                            {outsideStatus.map((service, index) => (
                                <span key={service.href}>
                                    {index > 0 && ' and '}
                                    <a
                                        href={service.href}
                                        className="underline underline-offset-4"
                                        rel="noreferrer"
                                    >
                                        {service.label}
                                    </a>
                                </span>
                            ))}
                            , which show their own status.
                        </p>
                    </section>
                </main>
            </div>
        </>
    );
}
