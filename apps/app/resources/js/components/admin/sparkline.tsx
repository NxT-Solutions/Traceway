/** A small trend beside a total. The number next to it carries the meaning, so the line is decoration. */
export default function Sparkline({ values }: { values: number[] }) {
    const width = 120;
    const height = 28;
    const most = Math.max(...values, 1);
    const step = values.length > 1 ? width / (values.length - 1) : width;
    const points = values
        .map(
            (value, index) =>
                `${(index * step).toFixed(1)},${(height - 2 - (value / most) * (height - 4)).toFixed(1)}`,
        )
        .join(' ');

    return (
        <svg
            className="spark"
            viewBox={`0 0 ${width} ${height}`}
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <polyline
                points={points}
                fill="none"
                stroke="var(--plot)"
                strokeWidth="2"
                strokeLinejoin="round"
                strokeLinecap="round"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}
