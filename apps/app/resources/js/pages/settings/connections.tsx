import { Head } from '@inertiajs/react';
import AvailableService from '@/components/connections/available-service';
import ConfirmEmailFirst from '@/components/connections/confirm-email-first';
import ConnectedAccountRow from '@/components/connections/connected-account-row';
import Heading from '@/components/heading';
import type {
    ConnectionDescription,
    ConnectionGroup,
    ConnectionsPageProps,
} from '@/types';

const intro =
    'Log in with an account you already have, and send routes to the apps on your devices. Each one says what Traceway keeps.';

/**
 * A disconnected service moves to Add a connection, so focus follows it there. Without that list, it goes to the
 * top of the page.
 */
const focusAfterDisconnect = ['connections-add', 'connections-title'];

/** The sentence Connect and Disconnect point to while the rider's email waits to be confirmed. */
const confirmEmailId = 'connections-confirm-email';

/** Sport apps first: most riders look for the app on their watch or bike computer. */
const groupOrder: ConnectionGroup[] = ['sport', 'accounts', 'maps'];

type Group = {
    key: ConnectionGroup;
    label: string;
    services: ConnectionDescription[];
};

function grouped(services: ConnectionDescription[]): Group[] {
    return groupOrder
        .map((key) => {
            const members = services.filter((service) => service.group === key);

            return {
                key,
                label: members[0]?.group_label ?? '',
                services: members,
            };
        })
        .filter((group) => group.services.length > 0);
}

export default function Connections({
    connected,
    available,
    hasPassword,
    emailVerified,
    verificationSent,
}: ConnectionsPageProps) {
    const groups = grouped(
        available.filter((service) => service.available && !service.connected),
    );
    const locked = !emailVerified;
    const nothingToShow = connected.length === 0 && groups.length === 0;

    return (
        <>
            <Head title="Connections" />

            <h1 id="connections-title" className="sr-only" tabIndex={-1}>
                Connections
            </h1>

            {locked && !nothingToShow ? (
                <div className="card">
                    <ConfirmEmailFirst
                        id={confirmEmailId}
                        sent={verificationSent}
                    />
                </div>
            ) : null}

            {connected.length === 0 ? null : (
                <div className="card">
                    <Heading
                        variant="small"
                        title="Connected"
                        description={intro}
                    />
                    <ul className="cx-list">
                        {connected.map((account) => (
                            <ConnectedAccountRow
                                key={account.slug}
                                account={account}
                                hasPassword={hasPassword}
                                focusAfter={focusAfterDisconnect}
                                locked={locked}
                                lockedBy={confirmEmailId}
                            />
                        ))}
                    </ul>
                </div>
            )}

            {groups.length === 0 ? null : (
                <div className="card">
                    <Heading
                        variant="small"
                        title="Add a connection"
                        id="connections-add"
                        focusable
                        description={
                            connected.length === 0
                                ? intro
                                : 'You allow Traceway on the service’s own page, then come back here.'
                        }
                    />
                    {connected.length === 0 ? (
                        <p className="cx-empty">
                            Nothing is connected yet. Choose Connect on a
                            service, and you can log in with it next time.
                        </p>
                    ) : null}
                    {groups.map((group) => (
                        <section
                            key={group.key}
                            className="cx-group"
                            aria-labelledby={`cx-group-${group.key}`}
                        >
                            <h3 id={`cx-group-${group.key}`}>{group.label}</h3>
                            <ul className="ai-apps">
                                {group.services.map((service) => (
                                    <AvailableService
                                        key={service.slug}
                                        service={service}
                                        locked={locked}
                                        lockedBy={confirmEmailId}
                                    />
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>
            )}

            {nothingToShow ? (
                <div className="card">
                    <Heading
                        variant="small"
                        title="Connections"
                        description="No services are set up on this Traceway yet."
                    />
                </div>
            ) : null}
        </>
    );
}
