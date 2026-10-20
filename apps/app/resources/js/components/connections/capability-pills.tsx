import { Check } from 'lucide-react';
import type { ConnectionCapability } from '@/types';

type Props = {
    capabilities: ConnectionCapability[];
    /** The id of the label the list is named by. */
    labelledBy?: string;
    /** Leave out what is not built yet. */
    nowOnly?: boolean;
};

/**
 * What a connection does. What it does today has a tick. What comes later says Later in words, not only in a
 * fainter colour.
 */
export default function CapabilityPills({
    capabilities,
    labelledBy,
    nowOnly = false,
}: Props) {
    const shown = nowOnly
        ? capabilities.filter((capability) => !capability.later)
        : capabilities;

    if (shown.length === 0) {
        return null;
    }

    return (
        <ul className="cx-pills" aria-labelledby={labelledBy}>
            {shown.map((capability) =>
                capability.later ? (
                    <li key={capability.key} className="cx-pill cx-later">
                        {capability.label}{' '}
                        <span className="cx-later-word">Later</span>
                    </li>
                ) : (
                    <li key={capability.key} className="cx-pill">
                        <Check size={14} aria-hidden="true" />
                        {capability.label}
                    </li>
                ),
            )}
        </ul>
    );
}
