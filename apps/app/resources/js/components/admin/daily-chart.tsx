import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { count, day, longDay, short } from '@/components/admin/format';

export type DailyPoint = { date: string; value: number };

type TipProps = {
    active?: boolean;
    payload?: ReadonlyArray<{ payload?: DailyPoint }>;
    unit: string;
};

function Tip({ active, payload, unit }: TipProps) {
    const point = payload?.[0]?.payload;

    if (!active || point === undefined) {
        return null;
    }

    return (
        <div className="tip">
            <div className="t">{longDay(point.date)}</div>
            <div>
                <b>{count(point.value)}</b> {unit}
            </div>
        </div>
    );
}

/**
 * One measure over days: bars for counts, a line for a level such as active riders. One series, so the title
 * names it and there is no legend. A table beside it carries the same numbers for screen readers, so the chart
 * itself stays out of the tab order.
 */
export default function DailyChart({
    data,
    unit,
    kind = 'bar',
    label,
}: {
    data: DailyPoint[];
    unit: string;
    kind?: 'bar' | 'line';
    label: string;
}) {
    const axis = {
        dataKey: 'date',
        tickFormatter: day,
        tickLine: false,
        axisLine: false,
        minTickGap: 28,
        tickMargin: 8,
    } as const;
    const values = {
        tickFormatter: short,
        tickLine: false,
        axisLine: false,
        allowDecimals: false,
        width: 44,
    } as const;

    return (
        <figure className="plot" style={{ margin: 0 }}>
            <figcaption className="vh">{label}</figcaption>
            <ResponsiveContainer width="100%" height="100%">
                {kind === 'bar' ? (
                    <BarChart
                        data={data}
                        barCategoryGap={2}
                        accessibilityLayer={false}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis {...axis} />
                        <YAxis {...values} />
                        <Tooltip
                            content={<Tip unit={unit} />}
                            cursor={{ className: 'recharts-tooltip-cursor' }}
                            isAnimationActive={false}
                        />
                        <Bar
                            dataKey="value"
                            fill="var(--plot)"
                            radius={[4, 4, 0, 0]}
                            maxBarSize={18}
                            isAnimationActive={false}
                        />
                    </BarChart>
                ) : (
                    <AreaChart data={data} accessibilityLayer={false}>
                        <CartesianGrid vertical={false} />
                        <XAxis {...axis} />
                        <YAxis {...values} />
                        <Tooltip
                            content={<Tip unit={unit} />}
                            cursor={{ stroke: 'var(--muted)', strokeWidth: 1 }}
                            isAnimationActive={false}
                        />
                        <Area
                            dataKey="value"
                            type="monotone"
                            stroke="var(--plot)"
                            strokeWidth={2}
                            fill="var(--plot-soft)"
                            dot={false}
                            activeDot={{
                                r: 5,
                                fill: 'var(--plot)',
                                stroke: 'var(--bg)',
                                strokeWidth: 2,
                            }}
                            isAnimationActive={false}
                        />
                    </AreaChart>
                )}
            </ResponsiveContainer>
            <table className="vh">
                <caption>{label}</caption>
                <thead>
                    <tr>
                        <th scope="col">Day</th>
                        <th scope="col">{unit}</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((point) => (
                        <tr key={point.date}>
                            <td>{longDay(point.date)}</td>
                            <td>{point.value}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}
