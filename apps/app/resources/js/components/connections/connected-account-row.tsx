import { TriangleAlert } from 'lucide-react';
import { useId } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import CapabilityPills from '@/components/connections/capability-pills';
import ConnectLink from '@/components/connections/connect-link';
import DisconnectBlocked from '@/components/connections/disconnect-blocked';
import DisconnectButton from '@/components/connections/disconnect-button';
import type { ConnectedAccount } from '@/types';

function written(value: string): string {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

/** When it was connected, and when the rider last used it. */
function history(account: ConnectedAccount): string {
    const parts = [
        account.connected_at === null
            ? 'Connected'
            : `Connected ${written(account.connected_at)}`,
    ];

    if (account.last_signed_in_at !== null) {
        parts.push(`Last used to log in ${written(account.last_signed_in_at)}`);
    }

    if (account.last_sent_at !== null) {
        parts.push(`Last route sent ${written(account.last_sent_at)}`);
    }

    return parts.join(' · ');
}

/**
 * One connected account: who it is at the service, what it does now, and how to connect it again or
 * disconnect it.
 */
export default function ConnectedAccountRow({
    account,
    hasPassword,
    focusAfter,
    locked = false,
    lockedBy,
}: {
    account: ConnectedAccount;
    hasPassword: boolean;
    /** Ids of what takes focus once this row is disconnected and gone. */
    focusAfter?: string[];
    /** True while the rider cannot connect or disconnect, such as before they confirm their email. */
    locked?: boolean;
    /** The id of the sentence that says why. */
    lockedBy?: string;
}) {
    const id = useId();
    const blocked = account.can_disconnect ? null : account.disconnect_blocked;
    const why = [
        blocked === null ? null : `${id}-blocked`,
        locked ? (lockedBy ?? null) : null,
    ].filter((reason) => reason !== null);

    return (
        <li className="cx-row">
            <div className="ai-app-head">
                <span className="ai-app-icon">
                    <ConnectionMark slug={account.slug} size={20} />
                </span>
                <div className="ai-app-name">
                    <h3 className="cx-name">{account.label}</h3>
                    {account.account_label === null ? null : (
                        <span className="cx-account">
                            {account.account_label}
                        </span>
                    )}
                    <small>{history(account)}</small>
                </div>
            </div>

            <span className="vh" id={`${id}-does`}>
                What {account.label} does
            </span>
            <CapabilityPills
                capabilities={account.capabilities}
                labelledBy={`${id}-does`}
                nowOnly
            />

            {account.needs_reconnect ? (
                <p className="cx-warn">
                    <TriangleAlert size={16} aria-hidden="true" />
                    <span>
                        Traceway lost its link to {account.label}. Connect it
                        again.
                    </span>
                </p>
            ) : null}

            {account.available ? null : (
                <p className="cx-note">
                    {account.label} is not available on Traceway right now. You
                    can still disconnect it.
                </p>
            )}

            {blocked === null ? null : (
                <DisconnectBlocked
                    id={`${id}-blocked`}
                    message={blocked}
                    hasPassword={hasPassword}
                />
            )}

            <div className="cx-buttons">
                {account.needs_reconnect && account.available ? (
                    <ConnectLink
                        slug={account.slug}
                        label={account.label}
                        again
                        disabled={locked}
                        describedBy={locked ? lockedBy : undefined}
                    />
                ) : null}
                <DisconnectButton
                    slug={account.slug}
                    label={account.label}
                    withdrawsAccess={account.withdraws_access}
                    sendsRoutes={account.capabilities.some(
                        (capability) =>
                            capability.key === 'send_routes' &&
                            !capability.later,
                    )}
                    manageUrl={account.manage_url}
                    disabled={!account.can_disconnect || locked}
                    describedBy={why.length === 0 ? undefined : why.join(' ')}
                    focusAfter={focusAfter}
                />
            </div>
        </li>
    );
}
