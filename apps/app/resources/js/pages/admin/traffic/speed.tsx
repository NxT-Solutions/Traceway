import { count, duration } from '@/components/admin/format';
import Rating from '@/components/admin/rating';
import TrafficPage from '@/components/admin/traffic-page';
import type { AdminSpeed, Vital } from '@/types/admin';

function VitalCell({
    vital,
    format,
}: {
    vital: Vital;
    format: (value: number) => string;
}) {
    if (vital === null) {
        return <span className="dim">Not measured</span>;
    }

    return (
        <Rating level={vital.rating} label={vital.label}>
            {format(vital.value)}
        </Rating>
    );
}

function shift(value: number): string {
    return value.toFixed(2);
}

function Speed({ speed }: { speed: AdminSpeed }) {
    return (
        <>
            <section className="panel" aria-labelledby="vitals-h">
                <div className="panel-head">
                    <div>
                        <h2 id="vitals-h">Core Web Vitals</h2>
                        <p>
                            How fast the site's pages felt over the last{' '}
                            {speed.days} days, for people who said yes to
                            counting. Each figure is what three in four page
                            loads did at least as well as. Good is up to 2.5 s
                            for the largest paint, 200 ms to answer a tap, and
                            0.1 of layout shift; poor is over 4 s, 500 ms, and
                            0.25.
                        </p>
                    </div>
                </div>
                <div
                    className="tbl-wrap"
                    role="region"
                    aria-labelledby="vitals-h"
                    // biome-ignore lint/a11y/noNoninteractiveTabindex: a table wider than the phone scrolls with the keyboard.
                    tabIndex={0}
                >
                    <table aria-labelledby="vitals-h">
                        <thead>
                            <tr>
                                <th scope="col">Page</th>
                                <th scope="col" className="r">
                                    Loads
                                </th>
                                <th scope="col">Largest paint</th>
                                <th scope="col">Answer to a tap</th>
                                <th scope="col">Layout shift</th>
                            </tr>
                        </thead>
                        <tbody>
                            {speed.vitals.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="dim">
                                        Nothing measured in these days.
                                    </td>
                                </tr>
                            ) : (
                                speed.vitals.map((page) => (
                                    <tr key={page.path}>
                                        <td>
                                            <code>{page.path}</code>
                                        </td>
                                        <td className="r">
                                            {count(page.loads)}
                                        </td>
                                        <td>
                                            <VitalCell
                                                vital={page.lcp}
                                                format={duration}
                                            />
                                        </td>
                                        <td>
                                            <VitalCell
                                                vital={page.inp}
                                                format={duration}
                                            />
                                        </td>
                                        <td>
                                            <VitalCell
                                                vital={page.cls}
                                                format={shift}
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="panel" aria-labelledby="timings-h">
                <div className="panel-head">
                    <div>
                        <h2 id="timings-h">Load times</h2>
                        <p>
                            How long a page took to load in full, and to show
                            its first text or image, for three in four loads.
                        </p>
                    </div>
                </div>
                <div
                    className="tbl-wrap"
                    role="region"
                    aria-labelledby="timings-h"
                    // biome-ignore lint/a11y/noNoninteractiveTabindex: a table wider than the phone scrolls with the keyboard.
                    tabIndex={0}
                >
                    <table aria-labelledby="timings-h">
                        <thead>
                            <tr>
                                <th scope="col">Page</th>
                                <th scope="col" className="r">
                                    Loads
                                </th>
                                <th scope="col" className="r">
                                    Loaded
                                </th>
                                <th scope="col" className="r">
                                    First paint
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {speed.timings.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="dim">
                                        Nothing measured in these days.
                                    </td>
                                </tr>
                            ) : (
                                speed.timings.map((page) => (
                                    <tr key={page.path}>
                                        <td>
                                            <code>{page.path}</code>
                                        </td>
                                        <td className="r">
                                            {count(page.loads)}
                                        </td>
                                        <td className="r">
                                            {page.load_ms === null
                                                ? 'Not measured'
                                                : duration(page.load_ms)}
                                        </td>
                                        <td className="r">
                                            {page.first_paint_ms === null
                                                ? 'Not measured'
                                                : duration(page.first_paint_ms)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </>
    );
}

export default function TrafficSpeed({ insights }: { insights?: AdminSpeed }) {
    return (
        <TrafficPage title="Admin speed" insights={insights}>
            {(speed) => <Speed speed={speed} />}
        </TrafficPage>
    );
}
