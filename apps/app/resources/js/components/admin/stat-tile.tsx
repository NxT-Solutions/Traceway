import { count } from '@/components/admin/format';

export default function StatTile({
    label,
    value,
    detail,
}: {
    label: string;
    value: number | string;
    detail?: string;
}) {
    return (
        <div className="tile">
            <span className="k">{label}</span>
            <span className="v">
                {typeof value === 'number' ? count(value) : value}
            </span>
            {detail ? <span className="d">{detail}</span> : null}
        </div>
    );
}
