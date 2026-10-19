import DailyChart from '@/components/admin/daily-chart';
import RankedList from '@/components/admin/ranked-list';
import { status } from '@/components/admin/traffic';
import TrafficPage from '@/components/admin/traffic-page';
import type { AdminRequests, Ranked } from '@/types/admin';

const addressKinds: Record<string, string> = {
    noRecord: 'Nothing known',
    unknown: 'Unknown',
    searchEngine: 'Search engine',
    badHost: 'Known bad host',
    tor: 'Tor',
    greylist: 'Greylisted',
    whitelist: 'Allowed',
    monitoringService: 'Monitoring service',
    backupService: 'Backup service',
    mobilePlatform: 'Mobile platform',
    scan: 'Scanner',
    securityScanner: 'Security scanner',
};

function addressKind(items: Ranked[]): Ranked[] {
    return items.map((item) => ({
        label: addressKinds[item.label] ?? item.label,
        count: item.count,
    }));
}

function Requests({ requests }: { requests: AdminRequests }) {
    return (
        <>
            <section className="panel" aria-labelledby="hours-h">
                <div className="panel-head">
                    <div>
                        <h2 id="hours-h">Last {requests.hours.length} hours</h2>
                        <p>
                            Everything that reached traceway.online each hour,
                            in your time. Cloudflare keeps hours for three days.
                        </p>
                    </div>
                </div>
                <DailyChart
                    hourly
                    label={`Requests each hour, last ${requests.hours.length} hours`}
                    unit="requests"
                    data={requests.hours.map((point) => ({
                        date: point.time,
                        value: point.requests,
                    }))}
                />
            </section>

            <section className="panel" aria-labelledby="year-h">
                <div className="panel-head">
                    <div>
                        <h2 id="year-h">Last {requests.year_days} days</h2>
                        <p>
                            Requests each day, for as long as Cloudflare has
                            counted them, up to a year.
                        </p>
                    </div>
                </div>
                <DailyChart
                    label={`Requests each day, last ${requests.year_days} days`}
                    unit="requests"
                    data={requests.year.map((point) => ({
                        date: point.date,
                        value: point.requests,
                    }))}
                />
            </section>

            <section className="panel" aria-labelledby="kinds-h">
                <div className="panel-head">
                    <div>
                        <h2 id="kinds-h">By kind, last {requests.days} days</h2>
                        <p>
                            How Traceway answered, and what asked. Browsers
                            count pages viewed, bots included.
                        </p>
                    </div>
                </div>
                <div className="grid2">
                    <section className="panel" aria-labelledby="statuses-h">
                        <div className="panel-head">
                            <h3 id="statuses-h">Answers</h3>
                        </div>
                        <RankedList
                            items={requests.statuses
                                .slice(0, 10)
                                .map((item) => ({
                                    label: status(item.status),
                                    count: item.count,
                                }))}
                            empty="No requests in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="types-h">
                        <div className="panel-head">
                            <h3 id="types-h">Kinds of file</h3>
                        </div>
                        <RankedList
                            items={requests.content_types}
                            empty="No requests in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="browsers-h">
                        <div className="panel-head">
                            <h3 id="browsers-h">Browsers</h3>
                        </div>
                        <RankedList
                            items={requests.browsers}
                            empty="No pages viewed in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="http-h">
                        <div className="panel-head">
                            <h3 id="http-h">HTTP versions</h3>
                        </div>
                        <RankedList
                            items={requests.http_versions}
                            empty="No requests in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="addresses-h">
                        <div className="panel-head">
                            <div>
                                <h3 id="addresses-h">Kinds of address</h3>
                                <p>
                                    What Cloudflare knows of where requests came
                                    from, never the address itself.
                                </p>
                            </div>
                        </div>
                        <RankedList
                            items={addressKind(requests.ip_classes)}
                            empty="No requests in these days."
                        />
                    </section>
                </div>
            </section>
        </>
    );
}

export default function TrafficRequests({
    insights,
}: {
    insights?: AdminRequests;
}) {
    return (
        <TrafficPage title="Admin requests" insights={insights}>
            {(requests) => <Requests requests={requests} />}
        </TrafficPage>
    );
}
