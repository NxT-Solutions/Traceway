import { count } from '@/components/admin/format';
import RankedList from '@/components/admin/ranked-list';
import StatTile from '@/components/admin/stat-tile';
import { share, status, top } from '@/components/admin/traffic';
import TrafficPage from '@/components/admin/traffic-page';
import type { AdminBots } from '@/types/admin';

function Bots({ bots }: { bots: AdminBots }) {
    const answered = bots.exposed.reduce((sum, path) => sum + path.count, 0);

    return (
        <section className="panel" aria-labelledby="bots-h">
            <div className="panel-head">
                <div>
                    <h2 id="bots-h">Last {bots.days} days</h2>
                    <p>
                        Who asked for traceway.online besides people: search
                        engines, AI assistants, and scanners. Cloudflare keeps
                        single requests for a month on the free plan.
                    </p>
                </div>
            </div>

            {answered > 0 ? (
                <div className="note bad" role="alert">
                    <b>
                        {count(answered)} requests for secret files were
                        answered. Look at these now:
                    </b>
                    <ul>
                        {bots.exposed.slice(0, 5).map((path) => (
                            <li key={path.label}>
                                <code>{path.label}</code>
                            </li>
                        ))}
                    </ul>
                    {bots.exposed.length > 5
                        ? `And ${bots.exposed.length - 5} more.`
                        : null}
                </div>
            ) : null}

            <div className="tiles">
                <StatTile
                    label="Verified bots"
                    value={bots.verified_total}
                    detail="checked by Cloudflare"
                />
                <StatTile
                    label="Reads for AI and search"
                    value={bots.reads_total}
                    detail="llms.txt, .md, robots.txt, /mcp"
                />
                <StatTile
                    label="Impostors"
                    value={bots.impostors_total}
                    detail={`${share(bots.impostors_blocked, bots.impostors_total)} blocked`}
                />
                <StatTile
                    label="Secret-file probes"
                    value={bots.probes_total}
                    detail={
                        answered > 0
                            ? `${count(answered)} answered`
                            : 'none answered'
                    }
                />
            </div>

            <div className="grid2">
                <section className="panel" aria-labelledby="verified-h">
                    <div className="panel-head">
                        <div>
                            <h3 id="verified-h">Verified bots</h3>
                            <p>
                                Search engines, AI search, and others Cloudflare
                                checked are who they say.
                            </p>
                        </div>
                    </div>
                    <RankedList
                        items={bots.verified}
                        empty="No verified bot in these days."
                    />
                </section>
                <section className="panel" aria-labelledby="readers-h">
                    <div className="panel-head">
                        <div>
                            <h3 id="readers-h">Who read what assistants use</h3>
                            <p>
                                llms.txt, the .md pages, robots.txt, sign-in
                                discovery, and /mcp.
                            </p>
                        </div>
                    </div>
                    <RankedList
                        items={bots.readers}
                        empty="Nothing read in these days."
                    />
                </section>
                <section className="panel" aria-labelledby="impostors-h">
                    <div className="panel-head">
                        <div>
                            <h3 id="impostors-h">Impostors</h3>
                            <p>
                                Scanners using a bot's name; Cloudflare could
                                not verify them.
                            </p>
                        </div>
                    </div>
                    <RankedList
                        items={top(bots.impostors, 10, 'other names')}
                        empty="No one used a bot's name in these days."
                    />
                </section>
                <section className="panel" aria-labelledby="probes-h">
                    <div className="panel-head">
                        <div>
                            <h3 id="probes-h">Probes for secret files</h3>
                            <p>
                                Requests for .env, keys, and .git, which
                                Traceway does not serve.
                            </p>
                        </div>
                    </div>
                    <RankedList
                        items={bots.probes.map((probe) => ({
                            label: status(probe.status),
                            count: probe.count,
                        }))}
                        empty="No probes in these days."
                    />
                </section>
            </div>

            <div className="panel-head">
                <div>
                    <h3 id="reads-h">What they read</h3>
                    <p>
                        Each file Traceway publishes for AI assistants and
                        search, and how it was answered.
                    </p>
                </div>
            </div>
            <div
                className="tbl-wrap"
                role="region"
                aria-labelledby="reads-h"
                // biome-ignore lint/a11y/noNoninteractiveTabindex: a table wider than the phone scrolls with the keyboard.
                tabIndex={0}
            >
                <table aria-labelledby="reads-h">
                    <thead>
                        <tr>
                            <th scope="col">Path</th>
                            <th scope="col">Method</th>
                            <th scope="col">Answer</th>
                            <th scope="col" className="r">
                                Requests
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {bots.reads.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="dim">
                                    Nothing read in these days.
                                </td>
                            </tr>
                        ) : (
                            bots.reads.slice(0, 25).map((read) => (
                                <tr
                                    key={`${read.method} ${read.path} ${read.status}`}
                                >
                                    <td>
                                        <code>{read.path}</code>
                                    </td>
                                    <td className="dim">{read.method}</td>
                                    <td className="dim">
                                        {status(read.status)}
                                    </td>
                                    <td className="r">{count(read.count)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function TrafficBots({ insights }: { insights?: AdminBots }) {
    return (
        <TrafficPage title="Admin AI and bots" insights={insights}>
            {(bots) => <Bots bots={bots} />}
        </TrafficPage>
    );
}
