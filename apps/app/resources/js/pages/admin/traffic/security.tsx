import DailyChart from '@/components/admin/daily-chart';
import { count } from '@/components/admin/format';
import RankedList from '@/components/admin/ranked-list';
import StatTile from '@/components/admin/stat-tile';
import { countries } from '@/components/admin/traffic';
import TrafficPage from '@/components/admin/traffic-page';
import type { AdminSecurity, Ranked } from '@/types/admin';

const events =
    'https://dash.cloudflare.com/?to=/:account/:zone/security/events';

const sourceNames: Record<string, string> = {
    firewallCustom: 'Custom rules',
    firewallManaged: 'Managed rules',
    firewallRules: 'Firewall rules',
    ratelimit: 'Rate limits',
    securitylevel: 'Security level',
    bic: 'Browser integrity check',
    hot: 'Hotlink protection',
    l7ddos: 'DDoS protection',
    validation: 'Request check',
    botFight: 'Bot fight mode',
    uablock: 'User agent block',
    zonelockdown: 'Zone lockdown',
    ip: 'IP access rules',
    country: 'Country rules',
};

function sourceName(items: Ranked[]): Ranked[] {
    return items.map((item) => ({
        label: sourceNames[item.label] ?? item.label,
        count: item.count,
    }));
}

const turnstileNames: Record<string, string> = {
    challenge_issued: 'Shown',
    challenge_non_interactive_solved: 'Passed without a click',
    challenge_interactive_solved: 'Passed with a click',
    challenge_non_interactive_siteverify_solved:
        'Passed without a click, confirmed by Traceway',
    challenge_interactive_siteverify_solved:
        'Passed with a click, confirmed by Traceway',
};

/** Turnstile's event types, such as challenge_non_interactive_solved, in plain words. */
function turnstileName(type: string): string {
    const known = turnstileNames[type];

    if (known) {
        return known;
    }

    const words = type.replace(/^challenge_/, '').replaceAll('_', ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function sum(items: Ranked[], test: (label: string) => boolean): number {
    return items
        .filter((item) => test(item.label))
        .reduce((total, item) => total + item.count, 0);
}

function DashboardLink() {
    return (
        <a href={events} target="_blank" rel="noreferrer">
            Security, then Events
            <span className="vh"> (opens in a new tab)</span>
        </a>
    );
}

function Security({ security }: { security: AdminSecurity }) {
    const threats = security.threat_days.reduce(
        (total, day) => total + day.count,
        0,
    );
    const peopleBlocked = security.people_pages.reduce(
        (total, page) => total + page.count,
        0,
    );
    const issued = sum(
        security.turnstile,
        (type) => type === 'challenge_issued',
    );
    const solved = sum(
        security.turnstile,
        (type) => type.endsWith('_solved') && !type.includes('siteverify'),
    );
    const failed = sum(security.turnstile, (type) =>
        /fail|timeout|error|expired/.test(type),
    );

    return (
        <>
            <section className="panel" aria-labelledby="security-h">
                <div className="panel-head">
                    <div>
                        <h2 id="security-h">Last {security.days} days</h2>
                        <p>
                            What Cloudflare stopped before it reached the
                            server. Which rule did it, and why, is in
                            Cloudflare's <DashboardLink />.
                        </p>
                    </div>
                </div>

                {peopleBlocked > 0 ? (
                    <div className="note bad" role="alert">
                        <b>
                            A rule stopped {count(peopleBlocked)} requests for
                            pages people use.
                        </b>{' '}
                        It may be turning away real visitors, not only scanners.
                        Check the rule in <DashboardLink />.
                        <ul>
                            {security.people_pages.slice(0, 5).map((page) => (
                                <li key={page.label}>
                                    <code>{page.label}</code>:{' '}
                                    {count(page.count)}{' '}
                                    {page.count === 1 ? 'block' : 'blocks'}
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                <div className="tiles">
                    <StatTile
                        label="Firewall blocks"
                        value={security.blocks}
                        detail="stopped by a rule"
                    />
                    <StatTile
                        label="Threats"
                        value={threats}
                        detail="known bad requests"
                    />
                    <StatTile
                        label="On pages people use"
                        value={peopleBlocked}
                        detail={`of ${count(security.events)} blocks listed`}
                    />
                    <StatTile
                        label="Sign-up checks"
                        value={issued}
                        detail={`last ${security.turnstile_days} days`}
                    />
                </div>
            </section>

            {security.block_days.length > 0 ? (
                <section className="panel" aria-labelledby="blocks-h">
                    <div className="panel-head">
                        <div>
                            <h2 id="blocks-h">Firewall blocks</h2>
                            <p>Requests a firewall rule stopped each day.</p>
                        </div>
                    </div>
                    <DailyChart
                        label={`Firewall blocks each day, last ${security.days} days`}
                        unit="blocks"
                        data={security.block_days.map((day) => ({
                            date: day.date,
                            value: day.count,
                        }))}
                    />
                </section>
            ) : null}

            <section className="panel" aria-labelledby="who-h">
                <div className="panel-head">
                    <div>
                        <h2 id="who-h">What was blocked</h2>
                        <p>
                            From the {count(security.events)} single blocks
                            Cloudflare listed
                            {security.events >= security.event_limit
                                ? ', the most it lists at once'
                                : ''}
                            . On the free plan the list is a sample, so these
                            add up to less than the blocks above.
                        </p>
                    </div>
                </div>
                <div className="grid2">
                    <section className="panel" aria-labelledby="sources-h">
                        <div className="panel-head">
                            <h3 id="sources-h">By kind of rule</h3>
                        </div>
                        <RankedList
                            items={sourceName(security.sources)}
                            empty="No blocks in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="bcountries-h">
                        <div className="panel-head">
                            <h3 id="bcountries-h">By country</h3>
                        </div>
                        <RankedList
                            items={countries(security.countries).slice(0, 10)}
                            empty="No blocks in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="paths-h">
                        <div className="panel-head">
                            <h3 id="paths-h">Paths asked for</h3>
                        </div>
                        <RankedList
                            items={security.paths}
                            empty="No blocks in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="agents-h">
                        <div className="panel-head">
                            <h3 id="agents-h">User agents</h3>
                        </div>
                        <RankedList
                            items={security.user_agents}
                            empty="No blocks in these days."
                        />
                    </section>
                    <section className="panel" aria-labelledby="rules-h">
                        <div className="panel-head">
                            <div>
                                <h3 id="rules-h">Rules</h3>
                                <p>
                                    By rule id, as Security, then Events shows
                                    it.
                                </p>
                            </div>
                        </div>
                        <RankedList
                            items={security.rules}
                            empty="No blocks in these days."
                        />
                    </section>
                </div>
            </section>

            {security.threat_days.length > 0 ? (
                <section className="panel" aria-labelledby="threats-h">
                    <div className="panel-head">
                        <div>
                            <h2 id="threats-h">Threats</h2>
                            <p>
                                Requests Cloudflare knew to be bad each day, and
                                where they came from.
                            </p>
                        </div>
                    </div>
                    <DailyChart
                        label={`Threats each day, last ${security.days} days`}
                        unit="threats"
                        data={security.threat_days.map((day) => ({
                            date: day.date,
                            value: day.count,
                        }))}
                    />
                    <div className="panel-head">
                        <h3 id="threat-countries-h">Where they came from</h3>
                    </div>
                    <RankedList
                        items={countries(security.threat_countries).slice(
                            0,
                            10,
                        )}
                        empty={`No threats in the last ${security.days} days.`}
                    />
                </section>
            ) : null}

            <section className="panel" aria-labelledby="turnstile-h">
                <div className="panel-head">
                    <div>
                        <h2 id="turnstile-h">Sign-up check</h2>
                        <p>
                            Turnstile under the sign-up and password reset
                            forms, last {security.turnstile_days} days.
                        </p>
                    </div>
                </div>
                <div className="tiles">
                    <StatTile label="Checks shown" value={issued} />
                    <StatTile
                        label="Passed"
                        value={solved}
                        detail="by a person or the browser"
                    />
                    <StatTile
                        label="Failed"
                        value={failed}
                        detail="stopped as a script"
                    />
                </div>
                <RankedList
                    items={security.turnstile.map((item) => ({
                        label: turnstileName(item.label),
                        count: item.count,
                    }))}
                    empty="No sign-up checks in these days."
                />
            </section>
        </>
    );
}

export default function TrafficSecurity({
    insights,
}: {
    insights?: AdminSecurity;
}) {
    return (
        <TrafficPage title="Admin security" insights={insights}>
            {(security) => <Security security={security} />}
        </TrafficPage>
    );
}
