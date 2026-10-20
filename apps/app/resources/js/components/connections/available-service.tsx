import { useId } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import CapabilityPills from '@/components/connections/capability-pills';
import ConnectLink from '@/components/connections/connect-link';
import type { ConnectionDescription } from '@/types';

/**
 * A service the rider has not connected: what it is for, what it does, what Traceway keeps, and a way to
 * connect it.
 */
export default function AvailableService({
    service,
    locked = false,
    lockedBy,
}: {
    service: ConnectionDescription;
    /** True while the rider cannot connect yet, such as before they confirm their email. */
    locked?: boolean;
    /** The id of the sentence that says why. */
    lockedBy?: string;
}) {
    const id = useId();

    return (
        <li className="ai-app cx-app">
            <div className="ai-app-head">
                <span className="ai-app-icon">
                    <ConnectionMark slug={service.slug} size={20} />
                </span>
                <div className="ai-app-name">
                    <h4 className="cx-name">{service.label}</h4>
                    <small>{service.purpose}</small>
                </div>
            </div>

            {service.note === null ? null : (
                <p className="cx-note">{service.note}</p>
            )}

            <div className="cx-part">
                <p className="cx-part-title" id={`${id}-does`}>
                    What it does
                </p>
                <CapabilityPills
                    capabilities={service.capabilities}
                    labelledBy={`${id}-does`}
                />
            </div>

            <div className="cx-part">
                <p className="cx-part-title" id={`${id}-keeps`}>
                    What Traceway keeps
                </p>
                <ul className="cx-keeps" aria-labelledby={`${id}-keeps`}>
                    {service.keeps.map((sentence) => (
                        <li key={sentence}>{sentence}</li>
                    ))}
                </ul>
            </div>

            <ConnectLink
                slug={service.slug}
                label={service.label}
                disabled={locked}
                describedBy={locked ? lockedBy : undefined}
            />
        </li>
    );
}
