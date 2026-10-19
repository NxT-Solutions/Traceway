import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DailyChart from '@/components/admin/daily-chart';
import { count, when } from '@/components/admin/format';
import RankedList from '@/components/admin/ranked-list';
import Sparkline from '@/components/admin/sparkline';
import StatTile from '@/components/admin/stat-tile';
import type { AdminOverview } from '@/types/admin';

const spans = [
    { key: 'day', label: 'Day', title: 'last day' },
    { key: 'week', label: '7 days', title: 'last 7 days' },
    { key: 'month', label: '30 days', title: 'last 30 days' },
] as const;

type Span = (typeof spans)[number]['key'];

function sum(days: { count: number }[]): number {
    return days.reduce((total, point) => total + point.count, 0);
}

function percent(part: number, whole: number): string {
    return whole === 0 ? '0%' : `${Math.round((part / whole) * 100)}%`;
}

export default function Overview({ overview }: { overview: AdminOverview }) {
    const [span, setSpan] = useState<Span>('month');
    const { totals } = overview;
    const shown = spans.find((option) => option.key === span) ?? spans[2];

    return (
        <>
            <Head title="Admin overview" />

            <section className="panel" aria-labelledby="riders-h">
                <div className="panel-head">
                    <div>
                        <h2 id="riders-h">Riders</h2>
                        <p>
                            Updated {when(overview.generated_at)}. Totals only.
                        </p>
                    </div>
                </div>
                <div className="tiles">
                    <StatTile
                        label="Riders"
                        value={totals.riders}
                        detail={`${percent(totals.verified, totals.riders)} confirmed their email`}
                    />
                    <StatTile
                        label="New this week"
                        value={totals.new_week}
                        detail={`${count(totals.new_month)} in 30 days`}
                    />
                    <StatTile
                        label="Active, 7 days"
                        value={totals.active_week}
                        detail={`${count(totals.active_day)} today · ${count(totals.active_month)} in 30 days`}
                    />
                    <StatTile
                        label="Online now"
                        value={totals.online_now}
                        detail="signed in, last 5 minutes"
                    />
                    <StatTile
                        label="Saved routes"
                        value={totals.saved_routes}
                        detail={`${count(totals.routes_month)} in 30 days`}
                    />
                    <StatTile
                        label="Watches linked"
                        value={totals.watches}
                        detail={`${count(totals.watches_active)} synced in 30 days`}
                    />
                    <StatTile
                        label="AI assistants"
                        value={totals.assistant_riders}
                        detail="riders with one connected"
                    />
                    <StatTile
                        label="Two-factor on"
                        value={percent(totals.two_factor, totals.riders)}
                        detail={`${count(totals.two_factor)} riders`}
                    />
                </div>
            </section>

            <section className="panel" aria-labelledby="signups-h">
                <div className="panel-head">
                    <div>
                        <h2 id="signups-h">New riders</h2>
                        <p>
                            Accounts opened each day, last {overview.days} days.
                        </p>
                    </div>
                    <span className="total">
                        <b>{count(sum(overview.signups))}</b>in {overview.days}{' '}
                        days
                    </span>
                </div>
                <DailyChart
                    label={`New riders each day, last ${overview.days} days`}
                    unit="new riders"
                    data={overview.signups.map((point) => ({
                        date: point.date,
                        value: point.count,
                    }))}
                />
            </section>

            <section className="panel" aria-labelledby="active-h">
                <div className="panel-head">
                    <div>
                        <h2 id="active-h">Active riders</h2>
                        <p>
                            Riders who used Traceway in the {shown.title},
                            written once a day.
                        </p>
                    </div>
                    <div
                        className="seg"
                        role="group"
                        aria-label="Active over"
                        style={{ ['--n' as string]: 3 }}
                    >
                        {spans.map((option) => (
                            <button
                                key={option.key}
                                type="button"
                                aria-pressed={span === option.key}
                                onClick={() => setSpan(option.key)}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
                <DailyChart
                    kind="line"
                    label={`Active riders over the ${shown.title}, each day`}
                    unit="active riders"
                    data={overview.active.map((point) => ({
                        date: point.date,
                        value: point[span],
                    }))}
                />
            </section>

            <section className="panel" aria-labelledby="saved-h">
                <div className="panel-head">
                    <div>
                        <h2 id="saved-h">Saved routes</h2>
                        <p>Routes saved each day, last {overview.days} days.</p>
                    </div>
                    <span className="total">
                        <b>{count(sum(overview.saved))}</b>in {overview.days}{' '}
                        days
                    </span>
                </div>
                <DailyChart
                    label={`Routes saved each day, last ${overview.days} days`}
                    unit="routes saved"
                    data={overview.saved.map((point) => ({
                        date: point.date,
                        value: point.count,
                    }))}
                />
            </section>

            <div className="grid2">
                <section className="panel" aria-labelledby="sports-h">
                    <div className="panel-head">
                        <h2 id="sports-h">Routes by sport</h2>
                    </div>
                    <RankedList
                        items={overview.sports}
                        empty="No saved routes yet."
                    />
                </section>
                <section className="panel" aria-labelledby="assist-h">
                    <div className="panel-head">
                        <h2 id="assist-h">AI assistants</h2>
                    </div>
                    <RankedList
                        items={overview.assistants}
                        empty="No rider has connected an assistant."
                    />
                </section>
                <section className="panel" aria-labelledby="sends-h">
                    <div className="panel-head">
                        <h2 id="sends-h">Sent to apps</h2>
                    </div>
                    <RankedList
                        items={overview.sends}
                        empty="No route has been sent to Wahoo or Ride with GPS."
                    />
                </section>
            </div>

            <section className="panel" aria-labelledby="usage-h">
                <div className="panel-head">
                    <div>
                        <h2 id="usage-h">What riders use</h2>
                        <p>
                            Daily totals for the last 30 days. They count what
                            was done, never who did it.
                        </p>
                    </div>
                </div>
                <div className="tbl-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Feature</th>
                                <th scope="col" className="hide-sm">
                                    30 days
                                </th>
                                <th scope="col" className="r">
                                    Total
                                </th>
                                <th scope="col" className="r hide-sm">
                                    Today
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {overview.usage.map((metric) => (
                                <tr key={metric.metric}>
                                    <td>{metric.label}</td>
                                    <td className="hide-sm">
                                        <Sparkline
                                            values={metric.days.map(
                                                (point) => point.count,
                                            )}
                                        />
                                    </td>
                                    <td className="r">{count(metric.total)}</td>
                                    <td className="r hide-sm">
                                        {count(metric.days.at(-1)?.count ?? 0)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </>
    );
}
