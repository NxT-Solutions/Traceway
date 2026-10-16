import { Head, Link, usePage, usePoll } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type State = 'up' | 'waiting' | 'slow' | 'down';

type Day = { date: string; state: State | null };

type Component = {
    key: string;
    label: string;
    state: State | null;
    summary: string;
    latency_ms: number | null;
    uptime: number | null;
    days: Day[];
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

type StatusPage = {
    state: State | 'unknown';
    headline: string;
    checked_at: string | null;
    stale: boolean;
    notices: Notice[];
    groups: Group[];
};

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

function ago(iso: string | null): string {
    if (iso === null) {
        return 'Not checked yet';
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

function when(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    return new Date(iso).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
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

function DayBars({ component }: { component: Component }) {
    const bad = component.days.filter(
        (day) => day.state === 'slow' || day.state === 'down',
    ).length;
    const label =
        bad === 0
            ? `${component.label}: no problems in the last ${component.days.length} days`
            : `${component.label}: problems on ${bad} of the last ${component.days.length} days`;

    return (
        <div role="img" aria-label={label} className="flex h-8 gap-px">
            {component.days.map((day, index) => (
                <span
                    key={day.date}
                    title={`${day.date}: ${day.state === null ? 'No checks' : stateWord[day.state]}`}
                    className={cn(
                        'flex-1 rounded-[2px]',
                        stateColour[day.state ?? 'unknown'],
                        // A phone shows the last 30 days.
                        index < component.days.length - 30 && 'hidden sm:block',
                    )}
                />
            ))}
        </div>
    );
}

export default function Status() {
    const page = usePage<StatusPage>().props;

    usePoll(60_000, {
        only: ['state', 'headline', 'checked_at', 'stale', 'notices', 'groups'],
    });

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
                        <p className="text-muted-foreground" aria-live="polite">
                            {ago(page.checked_at)}. We check every 5 minutes.
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
                                        Since {when(notice.started_at)}
                                    </p>
                                </article>
                            ))}
                        </section>
                    )}

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
                                        <DayBars component={component} />
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
                                            {when(notice.started_at)} to{' '}
                                            {when(notice.resolved_at)}.
                                            Resolved.
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
                            Each bar is one day, with the worst moment of that
                            day. Traceway also runs on{' '}
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
