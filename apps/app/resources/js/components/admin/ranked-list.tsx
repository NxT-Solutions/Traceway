import { count } from '@/components/admin/format';

export type Ranked = { label: string; count: number };

/** Parts of a whole, largest first, as labelled bars that need no colour to read. */
export default function RankedList({
    items,
    empty,
}: {
    items: Ranked[];
    empty: string;
}) {
    if (items.length === 0) {
        return <p className="nothing">{empty}</p>;
    }

    const most = Math.max(...items.map((item) => item.count), 1);

    return (
        <ol className="rank">
            {items.map((item) => (
                <li key={item.label}>
                    <span className="label">{item.label}</span>
                    <span className="count">{count(item.count)}</span>
                    <span className="bar" aria-hidden="true">
                        <i style={{ width: `${(item.count / most) * 100}%` }} />
                    </span>
                </li>
            ))}
        </ol>
    );
}
