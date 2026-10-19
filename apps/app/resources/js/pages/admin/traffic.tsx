import { Deferred, Head } from '@inertiajs/react';
import DailyChart from '@/components/admin/daily-chart';
import { bytes, count, when } from '@/components/admin/format';
import RankedList from '@/components/admin/ranked-list';
import StatTile from '@/components/admin/stat-tile';
import { Loading, NotConnected } from '@/components/admin/traffic-states';
import TrafficTabs from '@/components/admin/traffic-tabs';
import type { AdminTraffic } from '@/types/admin';

function Report({ traffic }: { traffic: AdminTraffic }) {
    if (!traffic.connected) {
        return <NotConnected />;
    }

    const { totals } = traffic;
    const cachedShare =
        totals.requests === 0
            ? '0%'
            : `${Math.round((totals.cached / totals.requests) * 100)}%`;

    return (
        <>
            {traffic.error ? (
                <p className="note" role="alert">
                    <b>Cloudflare traffic is not available right now.</b>{' '}
                    {traffic.error}
                </p>
            ) : null}

            <section className="panel" aria-labelledby="traffic-h">
                <div className="panel-head">
                    <div>
                        <h2 id="traffic-h">Last {traffic.days} days</h2>
                        <p>
                            Updated {when(traffic.generated_at)}. Cloudflare's
                            totals, read once an hour.
                        </p>
                    </div>
                </div>
                <div className="tiles">
                    <StatTile
                        label="Visits to the site"
                        value={totals.visits}
                        detail={`${count(totals.page_loads)} pages read, with consent`}
                    />
                    <StatTile
                        label="Requests"
                        value={totals.requests}
                        detail={`${cachedShare} served from Cloudflare's cache`}
                    />
                    <StatTile label="Data sent" value={bytes(totals.bytes)} />
                    <StatTile
                        label="Threats stopped"
                        value={totals.threats}
                        detail="blocked before the server"
                    />
                </div>
            </section>

            {traffic.visit_days.length > 0 ? (
                <section className="panel" aria-labelledby="visits-h">
                    <div className="panel-head">
                        <div>
                            <h2 id="visits-h">Visits</h2>
                            <p>
                                Visits to the site's pages each day, from people
                                who said yes to counting.
                            </p>
                        </div>
                    </div>
                    <DailyChart
                        kind="line"
                        label={`Visits each day, last ${traffic.days} days`}
                        unit="visits"
                        data={traffic.visit_days.map((point) => ({
                            date: point.date,
                            value: point.visits,
                        }))}
                    />
                </section>
            ) : null}

            {traffic.request_days.length > 0 ? (
                <section className="panel" aria-labelledby="requests-h">
                    <div className="panel-head">
                        <div>
                            <h2 id="requests-h">Requests</h2>
                            <p>
                                Everything that reached traceway.online each
                                day: pages, the planner, watches, and
                                assistants.
                            </p>
                        </div>
                    </div>
                    <DailyChart
                        label={`Requests each day, last ${traffic.days} days`}
                        unit="requests"
                        data={traffic.request_days.map((point) => ({
                            date: point.date,
                            value: point.requests,
                        }))}
                    />
                </section>
            ) : null}

            <div className="grid2">
                <section className="panel" aria-labelledby="pages-h">
                    <div className="panel-head">
                        <h2 id="pages-h">Most read pages</h2>
                    </div>
                    <RankedList
                        items={traffic.top_pages}
                        empty="No visits counted yet."
                    />
                </section>
                <section className="panel" aria-labelledby="from-h">
                    <div className="panel-head">
                        <h2 id="from-h">Where visitors came from</h2>
                    </div>
                    <RankedList
                        items={traffic.top_referrers}
                        empty="No visits counted yet."
                    />
                </section>
                <section className="panel" aria-labelledby="countries-h">
                    <div className="panel-head">
                        <h2 id="countries-h">Countries</h2>
                    </div>
                    <RankedList
                        items={traffic.countries}
                        empty="No visits counted yet."
                    />
                </section>
            </div>
        </>
    );
}

export default function Traffic({ traffic }: { traffic?: AdminTraffic }) {
    return (
        <>
            <Head title="Admin traffic" />
            <TrafficTabs />
            <Deferred data="traffic" fallback={<Loading />}>
                {traffic ? <Report traffic={traffic} /> : <Loading />}
            </Deferred>
        </>
    );
}
