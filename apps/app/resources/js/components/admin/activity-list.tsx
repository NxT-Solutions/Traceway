import { Link } from '@inertiajs/react';
import { when } from '@/components/admin/format';
import { show } from '@/routes/admin/users';
import type { ActivityEntry } from '@/types/admin';

/**
 * Who did what to whose account, newest first. A deleted account shows as gone, a change from the server as the
 * server.
 */
export default function ActivityList({
    entries,
    showRider = true,
    empty,
}: {
    entries: ActivityEntry[];
    showRider?: boolean;
    empty: string;
}) {
    if (entries.length === 0) {
        return <p className="nothing">{empty}</p>;
    }

    return (
        <ul className="list">
            {entries.map((entry) => (
                <li key={entry.id}>
                    <span>
                        {entry.summary}
                        {showRider ? (
                            <>
                                {' · '}
                                {entry.rider ? (
                                    <Link
                                        className="underline underline-offset-2"
                                        href={show(entry.rider.id)}
                                    >
                                        {entry.rider.name}
                                    </Link>
                                ) : (
                                    <small>an account that is gone</small>
                                )}
                            </>
                        ) : null}
                    </span>
                    <small>
                        {entry.actor ? entry.actor.name : 'The server'} ·{' '}
                        {when(entry.at)}
                    </small>
                </li>
            ))}
        </ul>
    );
}
