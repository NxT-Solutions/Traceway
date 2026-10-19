import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';
import type { ReactNode } from 'react';

export type RatingLevel = 'good' | 'needs_work' | 'poor';

const icons = {
    good: CircleCheck,
    needs_work: CircleAlert,
    poor: CircleX,
};

/** A rating in the status colours, always with its icon and its word, so colour is never the only sign. */
export default function Rating({
    level,
    label,
    children,
}: {
    level: RatingLevel;
    label: string;
    children?: ReactNode;
}) {
    const Icon = icons[level];

    return (
        <span className={`rating ${level}`}>
            <Icon size={16} aria-hidden="true" />
            {children ? <span className="num">{children}</span> : null}
            <span className="word">{label}</span>
        </span>
    );
}
