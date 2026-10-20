import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ConnectionMark } from '@/components/connection-mark';
import { useFocusOnArrival } from '@/hooks/use-focus-on-arrival';
import { destroy as forgetPending } from '@/routes/connections/pending';
import type { PendingConnection } from '@/types';

type Props = {
    pending: PendingConnection;
    /**
     * Take focus once the page is ready, so a screen reader reads it before the form. Focus reads it, so it is not
     * a live note as well, which would read it twice.
     */
    focus?: boolean;
};

/**
 * Once the note is gone, its "Not now" button is too. Focus goes to the first service, or else the page title.
 */
function focusAfterDropping(): void {
    const target =
        document.querySelector<HTMLElement>('.sign-in-options .providers a') ??
        document.querySelector<HTMLElement>('h1[tabindex="-1"]');

    target?.focus();
}

/**
 * A service that came back with the email of an account the rider already has. It is connected once they log
 * in to that account, so nobody can take an account over by naming its email at a service.
 */
export default function PendingConnectionNotice({
    pending,
    focus = false,
}: Props) {
    const [dropping, setDropping] = useState(false);
    const notice = useRef<HTMLDivElement>(null);

    useFocusOnArrival(notice, focus);

    function drop(): void {
        router.delete(forgetPending.url(), {
            onStart: () => setDropping(true),
            onSuccess: focusAfterDropping,
            onFinish: () => setDropping(false),
        });
    }

    return (
        <div
            className="notice"
            role={focus ? undefined : 'status'}
            ref={notice}
            tabIndex={-1}
        >
            <ConnectionMark slug={pending.slug} size={20} />
            <div className="notice-body">
                <p>
                    You already have a Traceway account with{' '}
                    <b>{pending.email}</b>. Log in to it once to connect{' '}
                    {pending.label}.
                </p>
                <button
                    type="button"
                    className="text-btn"
                    disabled={dropping}
                    onClick={drop}
                >
                    Not now
                    <span className="vh">, do not connect {pending.label}</span>
                </button>
            </div>
        </div>
    );
}
